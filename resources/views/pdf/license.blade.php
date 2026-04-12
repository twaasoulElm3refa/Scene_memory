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
            padding: 36px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            max-width: 740px;
            margin: auto;
        }

        .header {
            text-align: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
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
            margin-bottom: 14px;
        }

        .label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .value {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
            margin-top: 3px;
        }

        .token-box {
            margin-top: 20px;
            padding: 15px;
            background: #f3f4f6;
            border-radius: 8px;
            font-size: 11px;
            word-break: break-all;
            border-left: 4px solid #6366f1;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #374151;
            margin: 22px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }

        .legal-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            font-size: 11.5px;
            color: #374151;
            line-height: 1.8;
        }

        .legal-box ul {
            margin: 8px 0 0 0;
            padding-left: 18px;
        }

        .legal-box li {
            margin-bottom: 6px;
        }

        .rights-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 16px;
            margin-top: 14px;
            font-size: 11.5px;
            color: #374151;
            line-height: 1.8;
        }

        .rights-box ul {
            margin: 8px 0 0 0;
            padding-left: 18px;
        }

        .rights-box li {
            margin-bottom: 6px;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            font-size: 11px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            background: #10b981;
            color: white;
            font-size: 11px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .badge-purple {
            display: inline-block;
            padding: 4px 10px;
            background: #6366f1;
            color: white;
            font-size: 10px;
            border-radius: 6px;
            margin-left: 8px;
        }

        .highlight {
            color: #059669;
            font-weight: bold;
        }

        .stamp {
            text-align: center;
            margin-top: 18px;
            font-size: 11px;
            color: #6b7280;
        }

        .stamp strong {
            display: block;
            font-size: 13px;
            color: #111827;
            margin-top: 4px;
        }
    </style>
</head>

<body>

<div class="card">

    {{-- ===== HEADER ===== --}}
    <div class="header">
        <h1>🛡️ License Certificate</h1>
        <p>Official Proof of Purchase &amp; Digital Ownership</p>
        <span class="badge">✔ VERIFIED PURCHASE</span>
        <span class="badge-purple">LEGALLY BINDING</span>
    </div>

    {{-- ===== ORDER DETAILS ===== --}}
    <div class="section-title">📋 Purchase Details</div>

    <div class="grid">

        <div class="item">
            <div class="label">Full Name</div>
            <div class="value">{{ $order->user->name }}</div>
        </div>

        <div class="item">
            <div class="label">Email Address</div>
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
            <div class="label">Purchase Date</div>
            <div class="value">{{ $order->paid_at }}</div>
        </div>

        <div class="item">
            <div class="label">Product / Item</div>
            <div class="value">{{ $order->product_name ?? 'Digital Item' }}</div>
        </div>

        <div class="item">
            <div class="label">License Type</div>
            <div class="value">{{ $order->license_type ?? 'Standard Personal License' }}</div>
        </div>

    </div>

    {{-- ===== LICENSE TOKEN ===== --}}
    <div class="section-title">🔑 License Token</div>
    <div class="token-box">
        <strong>Unique License Key — Do not share</strong><br><br>
        {{ $token }}
    </div>

    {{-- ===== GRANTED RIGHTS ===== --}}
    <div class="section-title">✅ Rights Granted to the Licensee</div>
    <div class="rights-box">
        <strong>By completing this purchase, the following rights are officially granted:</strong>
        <ul>
            <li>The right to <span class="highlight">download, install, and use</span> the purchased digital item on devices owned or controlled by the licensee.</li>
            <li>The right to use this certificate as <span class="highlight">legal proof of ownership</span> in any dispute, claim, or platform review (including Google Play, App Store, and similar platforms).</li>
            <li>The right to <span class="highlight">retain a permanent personal copy</span> of the purchased item, even if it is removed from any marketplace.</li>
            <li>The right to <span class="highlight">request a refund or replacement</span> in case the item is defective or misrepresented, subject to the seller's refund policy.</li>
            <li>The right to use the item's data and content <span class="highlight">for personal or internal business purposes</span> as permitted by the license type above.</li>
        </ul>
    </div>

    {{-- ===== LEGAL TERMS ===== --}}
    <div class="section-title">⚖️ Legal Terms &amp; Conditions</div>
    <div class="legal-box">
        <strong>Important — Please read carefully:</strong>
        <ul>
            <li><strong>Ownership:</strong> This certificate confirms that the licensee named above has legally purchased and owns the right to use this digital item under the terms of the license type indicated.</li>
            <li><strong>Non-Transferability:</strong> This license is <strong>non-transferable</strong> and is bound exclusively to the purchaser's account and identity. Resale or redistribution is strictly prohibited unless explicitly permitted.</li>
            <li><strong>No Unauthorized Copies:</strong> The licensee may not reproduce, redistribute, sublicense, or sell the item or any derivative thereof without prior written consent from the original seller.</li>
            <li><strong>Intellectual Property:</strong> All intellectual property rights in the original item remain with the original creator/seller. This license grants usage rights only, not ownership of the underlying IP.</li>
            <li><strong>Platform Compliance:</strong> This license is issued in compliance with applicable digital marketplace policies, including Google Play Developer Distribution Agreement, Apple App Store Guidelines, and relevant consumer protection laws.</li>
            <li><strong>Dispute Resolution:</strong> In the event of any dispute regarding this purchase (including platform strikes, takedowns, or account actions), this certificate along with the Transaction ID serves as binding proof of a legitimate, paid transaction.</li>
            <li><strong>Data Use:</strong> Any personal data collected during the purchase is processed in accordance with our Privacy Policy and applicable data protection regulations (including GDPR where applicable).</li>
            <li><strong>Governing Law:</strong> This agreement shall be governed by and construed in accordance with applicable laws. Any disputes shall be resolved through binding arbitration or the appropriate legal authority.</li>
        </ul>
    </div>

    {{-- ===== VERIFICATION STAMP ===== --}}
    <div class="stamp">
        This certificate was automatically generated and is cryptographically verifiable.<br>
        <strong>Issued by: {{ config('app.name') }} &mdash; {{ now()->format('Y') }}</strong>
    </div>

    {{-- ===== FOOTER ===== --}}
    <div class="footer">
        This document constitutes a legally binding record of digital ownership and purchase.
        Certificate ID: #{{ $order->id }}-{{ substr(md5($token), 0, 8) }}
    </div>

</div>

</body>
</html>
