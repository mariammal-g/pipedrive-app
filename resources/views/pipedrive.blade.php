<!DOCTYPE html>
<html>
<head>
    <title>Pipedrive Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@pipedrive/app-extensions-sdk@0/dist/index.umd.js"></script>
</head>
<body class="p-3">

    <h2>Customer: {{ $email }}</h2>

    @if (!empty($data))
        <!-- Tabs -->
        <ul class="nav nav-tabs" id="panelTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab">
                    Invoices ({{ count($data['invoices'] ?? []) }})
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab">
                    Transactions ({{ count($data['charges'] ?? []) }})
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content mt-3" id="panelTabsContent">
            <!-- Invoices -->
            <div class="tab-pane fade show active" id="invoices" role="tabpanel">
                @forelse ($data['invoices'] ?? [] as $invoice)
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Invoice #{{ $invoice['number'] ?? $invoice['id'] }}</h5>
                            <p><strong>Amount:</strong> ${{ number_format(($invoice['total'] ?? 0) / 100, 2) }}</p>
                            <p><strong>Status:</strong> {{ $invoice['status'] ?? 'N/A' }}</p>
                            <p><strong>Customer:</strong> {{ $invoice['customer'] ?? 'N/A' }}</p>
                            <p><strong>Date:</strong> {{ \Carbon\Carbon::createFromTimestamp($invoice['created'])->format('Y-m-d H:i:s') }}</p>
                            @if(!empty($invoice['hosted_invoice_url']))
                                <a href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" class="btn btn-primary btn-sm">
                                    View Invoice Payment Page
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p>No invoices found.</p>
                @endforelse
            </div>

            <!-- Transactions -->
            <div class="tab-pane fade" id="transactions" role="tabpanel">
                @forelse ($data['charges'] ?? [] as $charge)
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>Charge #{{ $charge['id'] }}</h5>
                            <p><strong>Amount:</strong> ${{ number_format(($charge['amount'] ?? 0) / 100, 2) }}</p>
                            <p><strong>Status:</strong> {{ $charge['status'] ?? 'N/A' }}</p>
                            <p><strong>Customer:</strong> {{ $charge['customer'] ?? 'N/A' }}</p>
                            <p><strong>Date:</strong> {{ \Carbon\Carbon::createFromTimestamp($charge['created'])->format('Y-m-d H:i:s') }}</p>
                        </div>
                    </div>
                @empty
                    <p>No transactions found.</p>
                @endforelse
            </div>
        </div>
    @else
        <p>No data found for this email.</p>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (async function() {
            const sdk = await new AppExtensionsSDK().initialize();
        })();
    </script>
</body>
</html>
