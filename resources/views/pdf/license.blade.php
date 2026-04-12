<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>License Certificate</title>

    <style>
        body {
            font-family: DejaVu Sans;
            background: #f5f7fb;
            padding: 40px;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            max-width: 700px;
            margin: auto;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .header h1 {
            margin: 0;
            font-size: 26px;
            color: #111827;
        }

        .header p {
            color: #6b7280;
            margin-top: 5px;
            font-size: 13px;
        }

        .grid {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .item {
            width: 48%;
            margin-bottom: 12px;
        }

        .label {
            font-size: 12px;
            color: #6b7280;
        }

        .value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }

        .token-box {
            margin-top: 20px;
            padding: 15px;
            background: #f3f4f6;
            border-radius: 8px;
            font-size: 11px;
            word-break: break-all;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            font-size: 12px;
            color: #6b7280;
        }

        .badge {
            display: inline-block;
            padding: 5px 10px;
            background: #10b981;
            color: white;
            font-size: 11px;
            border-radius: 6px;
            margin-top: 10px;
        }
    </style>
</head>

<body>

<div class="card">

    <div class="header">
        <h1>License Certificate</h1>
        <p>Proof of Purchase & Digital Ownership</p>
        <span class="badge">VERIFIED PURCHASE</span>
    </div>

    <div class="grid">

        <div class="item">
            <div class="label">Name</div>
            <div class="value">{{ $order->user->name }}</div>
        </div>

        <div class="item">
            <div class="label">Email</div>
            <div class="value">{{ $order->user->email }}</div>
        </div>

        <div class="item">
            <div class="label">Order ID</div>
            <div class="value">#{{ $order->id }}</div>
        </div>

        <div class="item">
            <div class="label">Transaction ID</div>
            <div class="value">{{ $order->transaction_id }}</div>
        </div>

        <div class="item">
            <div class="label">Amount Paid</div>
            <div class="value">${{ $order->amount }}</div>
        </div>

        <div class="item">
            <div class="label">Paid At</div>
            <div class="value">{{ $order->paid_at }}</div>
        </div>

    </div>

    <div class="token-box">
        <strong>License Token</strong><br><br>
        {{ $token }}
    </div>

    <div class="footer">
        This document confirms your legal ownership of the purchased digital item.
    </div>

</div>

</body>
</html>
