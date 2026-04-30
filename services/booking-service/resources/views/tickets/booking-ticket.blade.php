<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Submeet Ticket</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            margin: 0;
            padding: 32px;
            background: #f8fafc;
        }

        .ticket {
            border: 2px solid #1d4ed8;
            border-radius: 24px;
            background: #ffffff;
            overflow: hidden;
        }

        .header {
            padding: 28px 32px;
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%);
            color: #ffffff;
        }

        .chip {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 12px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
        }

        .title {
            font-size: 30px;
            font-weight: 700;
            margin: 18px 0 8px;
        }

        .muted {
            color: #64748b;
            font-size: 13px;
            line-height: 1.65;
        }

        .content {
            padding: 30px 32px;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .grid td {
            width: 50%;
            vertical-align: top;
            padding: 0 12px 18px 0;
        }

        .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            color: #64748b;
            margin-bottom: 8px;
        }

        .value {
            font-size: 16px;
            font-weight: 700;
            color: #020617;
            line-height: 1.5;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .items th,
        .items td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 0;
            text-align: left;
            font-size: 13px;
        }

        .items th {
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 11px;
        }

        .footer {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
        }

        .qr-wrap {
            text-align: right;
        }

        .qr-wrap img {
            width: 168px;
            height: 168px;
        }
    </style>
</head>
<body>
<div class="ticket">
    <div class="header">
        <div class="chip">Submeet Ticket</div>
        <div class="title">{{ $snapshot?->event_title ?? 'Событие' }}</div>
        <div>{{ $snapshot?->hall_name ?? 'Площадка уточняется' }}</div>
        @if($snapshot?->hall_address)
            <div style="margin-top: 6px; opacity: 0.86;">{{ $snapshot->hall_address }}</div>
        @endif
    </div>

    <div class="content">
        <table class="grid">
            <tr>
                <td>
                    <div class="label">Код билета</div>
                    <div class="value">{{ $ticketCode }}</div>
                </td>
                <td>
                    <div class="label">Бронь</div>
                    <div class="value">#{{ $booking->id }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Начало</div>
                    <div class="value">{{ optional($snapshot?->starts_at)->format('d.m.Y H:i') ?? 'Уточняется' }}</div>
                </td>
                <td>
                    <div class="label">Возрастной рейтинг</div>
                    <div class="value">{{ $snapshot?->event_age_rating_label ?? '0+' }}</div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
            <tr>
                <th>Позиция</th>
                <th>Кол-во</th>
                <th>Сумма</th>
            </tr>
            </thead>
            <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->label }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format((float) $item->total_price, 2, ',', ' ') }} ₽</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="footer">
            <table style="width: 100%;">
                <tr>
                    <td style="width: 64%; vertical-align: top;">
                        <div class="label">Важно</div>
                        <div class="muted">
                            Покажите этот PDF или QR-код на входе. Билет сформирован автоматически после успешной оплаты.
                        </div>
                    </td>
                    <td class="qr-wrap">
                        <img src="{{ $qrDataUri }}" alt="Ticket QR code">
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
</body>
</html>
