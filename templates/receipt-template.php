<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
            background: #fff;
        }
        .receipt-container {
            max-width: 800px;
            margin: auto;
            padding: 20px;
            border: 1px solid #eee;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            font-size: 16px;
            line-height: 24px;
            color: #555;
        }
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        .receipt-header .company-details {
            text-align: left;
        }
        .receipt-header .receipt-title {
            text-align: right;
        }
        .receipt-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .receipt-details .billing-details,
        .receipt-details .shipping-details,
        .receipt-details .receipt-meta {
            width: 32%;
        }
        .receipt-total {
            text-align: right;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, .items-table td {
            border-bottom: 1px solid #eee;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background: #f9f9f9;
        }
        .totals-table {
            width: 100%;
            margin-top: 20px;
        }
        .totals-table td {
            padding: 5px 0;
        }
        .totals-table .label {
            text-align: right;
            padding-right: 20px;
        }
        .totals-table .amount {
            text-align: right;
        }
        .payment-instructions, .terms {
            margin-top: 30px;
        }
        .qr-code {
            text-align: right;
        }
        @media print {
            body {
                background: #fff;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Content will be dynamically generated -->
    </div>
</body>
</html>
