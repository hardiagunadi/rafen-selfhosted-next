<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cek apakah semua atribut di radreply/radcheck/radgroupreply
 * sudah terdaftar di FreeRADIUS dictionary lokal.
 *
 * Jika ada atribut Mikrotik- yang belum terdaftar, command ini
 * akan memperingatkan admin dan secara opsional menambahkannya
 * ke /etc/freeradius/3.0/dictionary lalu restart FreeRADIUS.
 *
 * Jalankan: php artisan radius:check-dictionary [--fix]
 */
class CheckRadiusDictionary extends Command
{
    protected $signature = 'radius:check-dictionary {--fix : Tambah atribut yang hilang ke dictionary dan restart FreeRADIUS}';

    protected $description = 'Cek atribut RADIUS di database sudah terdaftar di FreeRADIUS dictionary';

    /** Path ke dictionary lokal FreeRADIUS */
    private string $dictionaryPath = '/etc/freeradius/3.0/dictionary';

    /** Path backup dictionary di repo */
    private string $backupPath = '/var/www/rafen/freeradius-config/dictionary';

    public function handle(): int
    {
        // Ambil semua atribut unik dari tabel RADIUS
        $usedAttributes = collect()
            ->merge(DB::table('radreply')->distinct()->pluck('attribute'))
            ->merge(DB::table('radcheck')->distinct()->pluck('attribute'))
            ->merge(DB::table('radgroupreply')->distinct()->pluck('attribute'))
            ->unique()
            ->sort()
            ->values();

        // Baca dari backup repo — selalu disync setelah setiap perubahan,
        // bisa dibaca langsung tanpa sudo (berbeda dengan /etc/freeradius/ yang milik freerad).
        if (! file_exists($this->backupPath)) {
            $this->error("Dictionary backup tidak ditemukan: {$this->backupPath}");

            return self::FAILURE;
        }

        $dictContent = file_get_contents($this->backupPath);

        // Atribut standar FreeRADIUS (tidak perlu dicek di local dictionary)
        $standardAttributes = [
            'Cleartext-Password', 'NT-Password', 'MD5-Password', 'User-Password',
            'Framed-IP-Address', 'Framed-IP-Netmask', 'Framed-Pool', 'Framed-Route',
            'Session-Timeout', 'Idle-Timeout', 'Simultaneous-Use',
            'Reply-Message', 'Filter-Id', 'Service-Type',
            'Mikrotik-Recv-Limit', 'Mikrotik-Xmit-Limit', 'Mikrotik-Group',
            'Mikrotik-Wireless-Forward', 'Mikrotik-Wireless-Skip-Dot1x',
            'Mikrotik-Wireless-Enc-Algo', 'Mikrotik-Wireless-Enc-Key',
            'Mikrotik-Rate-Limit', 'Mikrotik-Realm', 'Mikrotik-Host-IP',
            'Mikrotik-Mark-Id', 'Mikrotik-Advertise-URL', 'Mikrotik-Advertise-Interval',
            'Mikrotik-Recv-Limit-Gigawords', 'Mikrotik-Xmit-Limit-Gigawords',
            'Mikrotik-Wireless-PSK', 'Mikrotik-Total-Limit',
            'Mikrotik-Total-Limit-Gigawords', 'Mikrotik-Address-List',
            'Mikrotik-Wireless-MPKey', 'Mikrotik-Wireless-Comment',
            'Mikrotik-Delegated-IPv6-Pool', 'Mikrotik-DHCP-Option-Set',
            'Mikrotik-DHCP-Option-Param-STR1', 'Mikrotik-DHCP-Option-Param-STR2',
            'Mikrotik-Wireless-VLANID', 'Mikrotik-Wireless-VLANIDtype',
            'Mikrotik-Wireless-Minsignal', 'Mikrotik-Wireless-Maxsignal',
            'Mikrotik-Switching-Filter',
        ];

        $missing = [];

        foreach ($usedAttributes as $attr) {
            // Cek di standard dictionary (nomor 1-30)
            if (in_array($attr, $standardAttributes)) {
                continue;
            }

            // Cek di local dictionary
            if (str_contains($dictContent, "ATTRIBUTE\t{$attr}") || str_contains($dictContent, "ATTRIBUTE {$attr}")) {
                continue;
            }

            $missing[] = $attr;
        }

        if (empty($missing)) {
            $this->info('Semua atribut RADIUS sudah terdaftar di dictionary.');

            return self::SUCCESS;
        }

        $this->warn('Atribut berikut ada di database tapi TIDAK terdaftar di dictionary:');
        foreach ($missing as $attr) {
            $this->line("  - {$attr}");
        }

        if (! $this->option('fix')) {
            $this->line('');
            $this->line('Jalankan dengan --fix untuk menambahkan otomatis: php artisan radius:check-dictionary --fix');

            return self::FAILURE;
        }

        // --fix: tambahkan ke dictionary
        $this->info('Menambahkan atribut yang hilang ke dictionary...');
        $added = $this->addMissingAttributes($missing, $dictContent);

        if ($added > 0) {
            $this->info("Ditambahkan {$added} atribut baru.");

            // Backup ke repo
            @copy($this->dictionaryPath, $this->backupPath);
            $this->line("  Backup: {$this->backupPath}");

            // Restart FreeRADIUS
            $restartCommand = (string) config('radius.restart_command', 'systemctl restart freeradius');
            exec($restartCommand.' 2>&1', $output, $code);
            if ($code === 0) {
                $this->info('FreeRADIUS berhasil di-restart.');
            } else {
                $this->error('FreeRADIUS restart gagal: '.implode("\n", $output));

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Tambahkan atribut yang hilang ke dalam blok BEGIN-VENDOR Mikrotik
     * di local dictionary. Nomor atribut dilanjutkan dari yang terakhir ada.
     */
    private function addMissingAttributes(array $missing, string $dictContent): int
    {
        // Cari nomor atribut tertinggi yang sudah ada di dictionary (lokal)
        preg_match_all('/^ATTRIBUTE\s+\S+\s+(\d+)\s+/m', $dictContent, $matches);
        $lastNum = count($matches[1]) > 0 ? max(array_map('intval', $matches[1])) : 30;

        $newLines = [];
        foreach ($missing as $attr) {
            $lastNum++;
            // Semua atribut Mikrotik yang belum diketahui type-nya → string
            $type = 'string';
            $newLines[] = "ATTRIBUTE\t{$attr}\t\t\t{$lastNum}\t{$type}";
            $this->line("  + {$attr} (id={$lastNum})");
        }

        // Sisipkan sebelum END-VENDOR Mikrotik
        $insertBlock = "\n# Atribut ditambahkan otomatis oleh radius:check-dictionary\n".implode("\n", $newLines)."\n";
        $newContent = str_replace('END-VENDOR	Mikrotik', $insertBlock.'END-VENDOR	Mikrotik', $dictContent);

        // Jika tidak ada blok END-VENDOR, append saja
        if ($newContent === $dictContent) {
            $newContent .= $insertBlock;
        }

        // Tulis ke backup repo dulu (writable tanpa sudo)
        file_put_contents($this->backupPath, $newContent);

        // Deploy ke /etc/freeradius/ lewat sudo cp
        exec("sudo cp {$this->backupPath} {$this->dictionaryPath} 2>&1", $out, $code);
        if ($code !== 0) {
            $this->error('Gagal copy ke '.$this->dictionaryPath.': '.implode(' ', $out));
            $this->line('Pastikan sudoers mengizinkan: deploy ALL=NOPASSWD:/bin/cp '.$this->backupPath.' '.$this->dictionaryPath);
        }

        return count($newLines);
    }
}
