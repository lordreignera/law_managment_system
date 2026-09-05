<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $documentTitle }} {{ $invoice->invoice_no }}</title>
    <style>
        body {
            color: #071426;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 0;
        }

        .kfms-finance-document {
            border: 1px solid #d8e2ef;
            padding: 28px;
        }

        .kfms-finance-document-header,
        .kfms-finance-document-meta,
        .kfms-finance-document-footer {
            display: table;
            width: 100%;
        }

        .kfms-finance-document-brand,
        .kfms-finance-document-title,
        .kfms-finance-document-meta > div,
        .kfms-finance-document-footer > span {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        .kfms-finance-document-brand img {
            max-height: 74px;
            max-width: 190px;
        }

        .kfms-finance-document-brand strong,
        .kfms-finance-document-title strong,
        .kfms-finance-document-title span,
        .kfms-finance-document-meta strong {
            display: block;
            font-weight: 800;
        }

        .kfms-finance-document-title {
            text-align: right;
        }

        .kfms-finance-document-title span {
            font-size: 24px;
            text-transform: uppercase;
        }

        .kfms-finance-document-title em,
        .kfms-finance-document-brand em,
        .kfms-finance-document-brand small,
        .kfms-finance-document-meta span,
        .kfms-finance-document-meta p {
            color: #51627a;
            font-style: normal;
        }

        .kfms-finance-document-meta {
            border-top: 1px solid #d8e2ef;
            margin-top: 24px;
            padding-top: 20px;
        }

        .kfms-finance-document-meta dl {
            margin: 0;
        }

        .kfms-finance-document-meta dt,
        .kfms-finance-document-meta dd {
            display: inline-block;
            margin: 0 0 8px;
            width: 48%;
        }

        .kfms-finance-document-meta dt {
            color: #51627a;
        }

        .kfms-finance-document-table {
            border-collapse: collapse;
            margin-top: 24px;
            width: 100%;
        }

        .kfms-finance-document-table th,
        .kfms-finance-document-table td {
            border: 1px solid #d8e2ef;
            padding: 12px;
        }

        .kfms-finance-document-table thead th {
            background: #eef5fb;
            text-align: left;
        }

        .kfms-finance-document-table .is-money {
            text-align: right;
            white-space: nowrap;
            width: 190px;
        }

        .kfms-finance-document-table tfoot th {
            text-align: right;
        }

        .kfms-finance-document-table tfoot .is-balance th {
            background: #071426;
            color: #fff;
        }

        .kfms-finance-document-note {
            border: 1px solid #d8e2ef;
            margin-top: 20px;
            padding: 14px;
        }

        .kfms-finance-document-footer {
            border-top: 1px solid #d8e2ef;
            color: #51627a;
            margin-top: 28px;
            padding-top: 12px;
        }

        .kfms-finance-document-footer span:last-child {
            text-align: right;
        }
    </style>
</head>
<body>
    @include('modules.finance.invoices.partials.document-content')
</body>
</html>
