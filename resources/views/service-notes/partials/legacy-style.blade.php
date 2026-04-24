<style>
    @page {
        size: 140mm 120mm;
        margin: 5mm 5mm;
    }

    body {
        margin: 0;
        padding: 0;
        font-family: Arial, Helvetica, sans-serif;
        font-size: 12px;
        color: #000;
    }

    .sheet {
        width: 100%;
    }

    .sheet-break {
        margin-top: 18px;
    }

    .nota {
        width: 100%;
        box-sizing: border-box;
        background: #fff;
    }

    .header {
        display: flex;
        align-items: center;
        margin-bottom: 3px;
        margin-top: 3px;
    }

    .logo {
        width: 55px;
        max-height: 55px;
        object-fit: contain;
    }

    .header-text {
        flex: 1;
        text-align: center;
    }

    .header-text .title {
        font-weight: bold;
        font-size: 15px;
    }

    .header-text .subtitle {
        font-size: 12px;
    }

    .divider {
        border: none;
        border-top: 2px solid #000;
        margin: 3px 0 2px;
    }

    .doc-title {
        text-align: center;
        font-weight: bold;
        font-size: 12px;
        letter-spacing: 1px;
        margin-bottom: 3px;
    }

    .top-info table {
        width: 100%;
        font-size: 12px;
    }

    .top-info td {
        vertical-align: top;
        padding: 0;
    }

    .bold {
        font-weight: bold;
    }

    .text-right {
        text-align: right;
    }

    .mt-3 {
        margin-top: 3px;
    }

    .section-title {
        font-weight: bold;
        font-size: 12px;
        border-bottom: 1px solid #000;
        margin-top: 4px;
        margin-bottom: 2px;
        padding-bottom: 0;
    }

    .detail-table {
        width: 100%;
        font-size: 12px;
    }

    .detail-table td {
        padding: 0;
        vertical-align: top;
    }

    .detail-table .lbl {
        width: 42%;
        color: #333;
    }

    .detail-table .sep {
        width: 4%;
    }

    .detail-table .val {
        width: 54%;
        font-weight: bold;
    }

    .amount-table {
        width: 100%;
        margin-top: 2px;
        font-size: 12px;
    }

    .amount-table td {
        padding: 1px 0;
    }

    .amount-table .label {
        width: 60%;
    }

    .amount-table .value {
        width: 40%;
        text-align: right;
        padding-right: 15px;
    }

    .total-row {
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        font-weight: bold;
    }

    .note-meta {
        font-size: 10px;
        text-align: right;
        margin-top: 4px;
        color: #555;
        font-style: italic;
    }

    .instruction-text {
        font-size: 11px;
        line-height: 1.35;
    }

    .transfer-table {
        width: 100%;
        font-size: 11px;
    }

    .transfer-table td {
        padding: 2px 0;
        vertical-align: top;
    }

    .transfer-index {
        width: 6%;
        font-weight: bold;
    }

    .transfer-detail {
        width: 94%;
    }

    .transfer-bank {
        font-weight: bold;
    }

    .transfer-branch {
        color: #555;
    }

    .no-print {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }

    @media print {
        .sheet-break {
            margin-top: 0;
            break-before: page;
            page-break-before: always;
        }

        .no-print {
            display: none !important;
        }
    }
</style>
