<?php

// Matriks role -> permission (PRD bagian 6). Sumber kebenaran runtime ada di
// tabel spatie (disinkronkan oleh RolesAndPermissionsSeeder); file ini hanya seed awal.
// Nuansa scope (G/B/O) dicek oleh Policy, bukan oleh daftar ini.
return [
    'invitation_ttl_days' => 7,

    'role_permissions' => [
        'owner' => [
            'structure.view', 'structure.manage', 'asset.manage', 'user.invite',
            'ticket.create', 'ticket.view', 'ticket.confirm_close',
            'change_request.create', 'estimate.approve', 'quota.view',
            'subscription.change_plan', 'payment_method.manage',
            'invoice.view', 'invoice.pay', 'report.view', 'audit.view',
        ],
        'brand_manager' => [
            'structure.view', 'user.invite', 'ticket.create', 'ticket.view',
            'ticket.confirm_close', 'change_request.create', 'quota.view', 'report.view',
        ],
        'outlet_manager' => [
            'structure.view', 'ticket.create', 'ticket.view', 'ticket.confirm_close', 'quota.view',
        ],
        'account_manager' => [
            'structure.view', 'structure.manage', 'asset.manage', 'user.invite',
            'ticket.create', 'ticket.view', 'ticket.manage', 'ticket.confirm_close',
            'ticket.internal_note', 'ticket.log_time', 'change_request.create',
            'estimate.create', 'quota.view', 'subscription.propose', 'invoice.view',
            'report.view', 'incident.manage', 'audit.view',
        ],
        'developer' => [
            'structure.view', 'asset.manage', 'ticket.create', 'ticket.view',
            'ticket.manage', 'ticket.internal_note', 'ticket.log_time',
            'estimate.create', 'quota.view', 'report.view', 'incident.manage',
        ],
        'finance' => [
            'structure.view', 'quota.view', 'invoice.view', 'invoice.manage', 'report.view',
        ],
    ],

    'attachments' => [
        'disk' => 'local', // privat; diakses hanya lewat signed URL
        'max_files' => 5,
        'max_kb' => 20480,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'pdf', 'txt', 'log'],
        'url_ttl_minutes' => 10,
    ],

    // Kategori tiket F&B. Prioritas gejala = saran awal sistem (Lampiran A butir 4).
    'ticket_categories' => [
        'pos' => ['label' => 'POS/Kasir', 'default_priority' => 'P3', 'symptoms' => [
            'cannot_transact' => ['label' => 'Tidak bisa transaksi', 'priority' => 'P1'],
            'receipt_printer' => ['label' => 'Struk tidak keluar', 'priority' => 'P2'],
            'slow' => ['label' => 'Lambat/hang', 'priority' => 'P3'],
            'other' => ['label' => 'Lainnya', 'priority' => 'P3'],
        ]],
        'online_ordering' => ['label' => 'Pemesanan Online', 'default_priority' => 'P3', 'symptoms' => [
            'cannot_order' => ['label' => 'Pelanggan tidak bisa memesan', 'priority' => 'P1'],
            'wrong_menu' => ['label' => 'Menu/harga tampil salah', 'priority' => 'P3'],
            'slow' => ['label' => 'Lambat', 'priority' => 'P3'],
            'other' => ['label' => 'Lainnya', 'priority' => 'P3'],
        ]],
        'payment' => ['label' => 'Pembayaran', 'default_priority' => 'P3', 'symptoms' => [
            'cannot_pay' => ['label' => 'Pembayaran tidak bisa diproses', 'priority' => 'P1'],
            'qris_failed' => ['label' => 'QRIS gagal', 'priority' => 'P2'],
            'wrong_amount' => ['label' => 'Nominal tidak sesuai', 'priority' => 'P2'],
            'other' => ['label' => 'Lainnya', 'priority' => 'P3'],
        ]],
        'kitchen' => ['label' => 'Printer/Layar Dapur', 'default_priority' => 'P3', 'symptoms' => [
            'no_orders_printed' => ['label' => 'Pesanan tidak tercetak/tampil', 'priority' => 'P2'],
            'other' => ['label' => 'Lainnya', 'priority' => 'P3'],
        ]],
        'delivery' => ['label' => 'Integrasi Delivery', 'default_priority' => 'P3', 'symptoms' => [
            'orders_not_synced' => ['label' => 'Pesanan tidak masuk', 'priority' => 'P2'],
            'menu_not_synced' => ['label' => 'Menu tidak sinkron', 'priority' => 'P3'],
            'other' => ['label' => 'Lainnya', 'priority' => 'P3'],
        ]],
        'website' => ['label' => 'Website', 'default_priority' => 'P3', 'symptoms' => [
            'down' => ['label' => 'Website tidak bisa dibuka', 'priority' => 'P2'],
            'content' => ['label' => 'Konten salah', 'priority' => 'P3'],
            'other' => ['label' => 'Lainnya', 'priority' => 'P3'],
        ]],
        'account' => ['label' => 'Akun/Akses', 'default_priority' => 'P3', 'symptoms' => [
            'cannot_login' => ['label' => 'Tidak bisa masuk', 'priority' => 'P3'],
            'other' => ['label' => 'Lainnya', 'priority' => 'P4'],
        ]],
        'other' => ['label' => 'Lainnya', 'default_priority' => 'P4', 'symptoms' => [
            'other' => ['label' => 'Lainnya', 'priority' => 'P4'],
        ]],
    ],

    // Target SLA Sprint 3 (PRD): response time & resolution time per prioritas.
    'sla' => [
        'P1' => [
            'response_minutes' => 15,
            'resolution_minutes' => 120, // 2 jam
            'calendar' => '24x7',
        ],
        'P2' => [
            'response_minutes' => 30,
            'resolution_minutes' => 240, // 4 jam kerja
            'calendar' => 'operating_hours',
        ],
        'P3' => [
            'response_minutes' => 120,    // 2 jam kerja
            'resolution_minutes' => 1440, // 24 jam kerja
            'calendar' => 'operating_hours',
        ],
        'P4' => [
            'response_minutes' => 480,    // 8 jam kerja
            'resolution_minutes' => 4320, // 72 jam kerja (3 hari)
            'calendar' => 'operating_hours',
        ],
        // Jam operasional default bila outlet tidak ditentukan (misal tiket website/brand) atau belum diisi.
        'default_operating_hours' => [
            'mon' => [['09:00', '21:00']],
            'tue' => [['09:00', '21:00']],
            'wed' => [['09:00', '21:00']],
            'thu' => [['09:00', '21:00']],
            'fri' => [['09:00', '21:00']],
            'sat' => [['09:00', '21:00']],
            'sun' => [['09:00', '21:00']],
        ],
        'default_timezone' => 'Asia/Jakarta',
    ],
];

