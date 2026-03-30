@extends('layouts.admin')

@section('title', 'Ekspor User')

@section('content')
<div class="card">
    <div class="card-header">
        <h4 class="mb-0">Ekspor User ke CSV</h4>
    </div>
    <div class="card-body">
        <form action="{{ route('tools.export-users.download') }}" method="GET">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Tipe User</label>
                        <select name="type" class="form-control">
                            <option value="ppp">PPP Users</option>
                            <option value="hotspot">Hotspot Users</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Filter Status Akun</label>
                        <select name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="enable">Enable</option>
                            <option value="disable">Disable</option>
                            <option value="isolir">Isolir</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-group w-100">
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-download mr-1"></i>Download CSV
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <hr>
        <div class="alert alert-info mb-0">
            <i class="fas fa-info-circle mr-1"></i>
            File CSV akan berisi data sesuai kepemilikan akun Anda. Password disertakan dalam file — jaga kerahasiaan file ekspor.
        </div>
    </div>
</div>
@endsection
