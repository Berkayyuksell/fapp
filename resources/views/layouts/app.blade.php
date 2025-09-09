<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>E-Fatura Yönetim</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* Navbar */
        .navbar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: #111 !important;
        }
        .nav-link {
            font-weight: 500;
            color: #444 !important;
            margin: 0 4px;
            transition: color 0.2s;
        }
        .nav-link:hover {
            color: #6d28d9 !important; /* accent mor */
        }
        .nav-link.active {
            color: #6d28d9 !important;
            font-weight: 600;
            border-bottom: 2px solid #6d28d9;
        }

        /* Sync Button - modern accent */
        .btn-sync {
            background-color: #6d28d9; /* mor accent */
            border: none;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 14px;
            transition: all 0.2s ease-in-out;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-sync:hover {
            background-color: #5b21b6;
            transform: translateY(-1px);
        }

        .loading {
            display: none;
        }
        .loading.show {
            display: inline-block;
        }

        /* Alerts */
        .alert {
            border-radius: 10px;
            font-weight: 500;
        }
    </style>

    @stack('styles')
</head>

<body class="bg-light">
    <!-- Header -->
    <nav class="navbar navbar-expand-lg mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route('invoices.outgoing') }}">
                📄 E-Fatura
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('invoices.outgoing') ? 'active' : '' }}" 
                           href="{{ route('invoices.outgoing') }}">
                            📤 Giden
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('invoices.incoming') ? 'active' : '' }}" 
                           href="{{ route('invoices.incoming') }}">
                            📥 Gelen
                        </a>
                    </li>

                </ul>


                <form action="{{ route('invoices.sync') }}" method="POST" id="syncForm">
                    @csrf
                    <button type="submit" class="btn btn-sync" id="syncBtn">
                        🔄 <span class="loading spinner-border spinner-border-sm" role="status"></span>
                        Senkronize
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Alerts -->
    <div class="container">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                ✅ {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                ❌ {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Sync loading efekti
        document.getElementById('syncForm').addEventListener('submit', function() {
            const syncBtn = document.getElementById('syncBtn');
            const loading = syncBtn.querySelector('.loading');

            syncBtn.disabled = true;
            loading.classList.add('show');
        });

        // Alert otomatik kapanma
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>

    @stack('scripts')
</body>
</html>
