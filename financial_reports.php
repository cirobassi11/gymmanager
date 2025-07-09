<?php
require_once 'config.php';
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Controllo accesso admin
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Gestione filtri temporali tramite URL parameter
$filter = $_GET['filter'] ?? '1m'; // Default: ultimo mese

// Calcola le date basate sul filtro
function getDateRangeFromFilter($filter) {
    $endDate = date('Y-m-d'); // Oggi
    
    switch($filter) {
        case '1w':
            $startDate = date('Y-m-d', strtotime('-7 days'));
            break;
        case '1m':
            $startDate = date('Y-m-d', strtotime('-30 days'));
            break;
        case '1y':
            $startDate = date('Y-m-d', strtotime('-365 days'));
            break;
        case '5y':
            $startDate = date('Y-m-d', strtotime('-1825 days')); // 5 anni = 5 * 365 giorni
            break;
        case 'all':
        default:
            // Trova la data più antica nei dati
            $stmt = $GLOBALS['conn']->prepare("
                SELECT LEAST(
                    (SELECT MIN(date) FROM PAYMENTS),
                    (SELECT MIN(maintenanceDate) FROM MAINTENANCES)
                ) as min_date
            ");
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $startDate = $result['min_date'] ?: date('Y-m-d', strtotime('-365 days'));
            break;
    }
    
    return [$startDate, $endDate];
}

list($startDate, $endDate) = getDateRangeFromFilter($filter);

// Funzione per ottenere le entrate giornaliere
function getDailyRevenue($conn, $startDate, $endDate) {
    $stmt = $conn->prepare("
        SELECT DATE(p.date) as payment_date, 
               SUM(p.amount) as daily_revenue,
               COUNT(p.paymentID) as payment_count
        FROM PAYMENTS p
        WHERE DATE(p.date) BETWEEN ? AND ?
        GROUP BY DATE(p.date)
        ORDER BY payment_date ASC
    ");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Funzione per ottenere le spese giornaliere per manutenzioni
function getDailyExpenses($conn, $startDate, $endDate) {
    $stmt = $conn->prepare("
        SELECT DATE(m.maintenanceDate) as expense_date,
               SUM(m.maintenanceCost) as daily_expenses,
               COUNT(m.maintenanceID) as maintenance_count
        FROM MAINTENANCES m
        WHERE m.maintenanceCost IS NOT NULL 
        AND DATE(m.maintenanceDate) BETWEEN ? AND ?
        GROUP BY DATE(m.maintenanceDate)
        ORDER BY expense_date ASC
    ");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Funzione per statistiche riepilogative
function getFinancialStats($conn, $startDate, $endDate) {
    // Totale entrate
    $stmt = $conn->prepare("
        SELECT SUM(amount) as total_revenue, COUNT(*) as total_payments
        FROM PAYMENTS 
        WHERE DATE(date) BETWEEN ? AND ?
    ");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $revenueStats = $stmt->get_result()->fetch_assoc();
    
    // Totale spese
    $stmt = $conn->prepare("
        SELECT SUM(maintenanceCost) as total_expenses, COUNT(*) as total_maintenances
        FROM MAINTENANCES 
        WHERE maintenanceCost IS NOT NULL AND DATE(maintenanceDate) BETWEEN ? AND ?
    ");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $expenseStats = $stmt->get_result()->fetch_assoc();
    
    // Entrate per tipo di abbonamento
    $stmt = $conn->prepare("
        SELECT m.name as membership_name, 
               SUM(p.amount) as revenue,
               COUNT(p.paymentID) as sales_count
        FROM PAYMENTS p
        JOIN SUBSCRIPTIONS s ON p.subscriptionID = s.subscriptionID
        JOIN MEMBERSHIPS m ON s.membershipID = m.membershipID
        WHERE DATE(p.date) BETWEEN ? AND ?
        GROUP BY m.membershipID, m.name
        ORDER BY revenue DESC
    ");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $membershipRevenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Top attrezzature per costi manutenzione
    $stmt = $conn->prepare("
        SELECT e.name as equipment_name,
               SUM(m.maintenanceCost) as total_cost,
               COUNT(m.maintenanceID) as maintenance_count
        FROM MAINTENANCES m
        JOIN EQUIPMENTS e ON m.equipmentID = e.equipmentID
        WHERE m.maintenanceCost IS NOT NULL AND DATE(m.maintenanceDate) BETWEEN ? AND ?
        GROUP BY e.equipmentID, e.name
        ORDER BY total_cost DESC
        LIMIT 5
    ");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $topExpenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Top 5 clienti che spendono di più
    $stmt = $conn->prepare("
        SELECT u.firstName, u.lastName, u.email,
               SUM(p.amount) as total_spent,
               COUNT(p.paymentID) as payment_count,
               AVG(p.amount) as avg_payment
        FROM PAYMENTS p
        JOIN USERS u ON p.customerID = u.userID
        WHERE DATE(p.date) BETWEEN ? AND ?
        GROUP BY p.customerID, u.firstName, u.lastName, u.email
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $topCustomers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return [
        'totalRevenue' => $revenueStats['total_revenue'] ?? 0,
        'totalPayments' => $revenueStats['total_payments'] ?? 0,
        'totalExpenses' => $expenseStats['total_expenses'] ?? 0,
        'totalMaintenances' => $expenseStats['total_maintenances'] ?? 0,
        'netProfit' => ($revenueStats['total_revenue'] ?? 0) - ($expenseStats['total_expenses'] ?? 0),
        'membershipRevenue' => $membershipRevenue,
        'topExpenses' => $topExpenses,
        'topCustomers' => $topCustomers
    ];
}

// Ottieni i dati
$dailyRevenue = getDailyRevenue($conn, $startDate, $endDate);
$dailyExpenses = getDailyExpenses($conn, $startDate, $endDate);
$stats = getFinancialStats($conn, $startDate, $endDate);

// Prepara dati per i grafici
function prepareDateRange($startDate, $endDate) {
    $dates = [];
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    while ($current <= $end) {
        $dates[] = $current->format('Y-m-d');
        $current->add(new DateInterval('P1D'));
    }
    
    return $dates;
}

$dateRange = prepareDateRange($startDate, $endDate);

// Prepara array per entrate con tutti i giorni
$revenueByDate = [];
foreach ($dailyRevenue as $revenue) {
    $revenueByDate[$revenue['payment_date']] = $revenue['daily_revenue'];
}

// Prepara array per spese con tutti i giorni
$expensesByDate = [];
foreach ($dailyExpenses as $expense) {
    $expensesByDate[$expense['expense_date']] = $expense['daily_expenses'];
}

// Crea array completi per i grafici
$chartDates = [];
$chartRevenue = [];
$chartExpenses = [];

foreach ($dateRange as $date) {
    $chartDates[] = date('d/m', strtotime($date));
    $chartRevenue[] = $revenueByDate[$date] ?? 0;
    $chartExpenses[] = $expensesByDate[$date] ?? 0;
}

// Funzione per ottenere il testo del periodo
function getPeriodText($filter) {
    switch($filter) {
        case '1w': return 'Ultima settimana';
        case '1m': return 'Ultimo mese';
        case '1y': return 'Ultimo anno';
        case '5y': return 'Ultimi 5 anni';
        case 'all': return 'Tutti i dati';
        default: return 'Periodo personalizzato';
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Report Finanziari</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
</head>
<body class="bg-light">
<div class="container py-5">
    <!-- Header -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h2>Report Finanziari</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Filtri Temporali -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Filtri Periodo</h5>
                
                <!-- Pulsanti filtro temporale -->
                <div class="btn-group" role="group" aria-label="Filtri temporali">
                    <a href="?filter=1w" class="btn <?= $filter === '1w' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        1 Settimana
                    </a>
                    <a href="?filter=1m" class="btn <?= $filter === '1m' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        1 Mese
                    </a>
                    <a href="?filter=1y" class="btn <?= $filter === '1y' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        1 Anno
                    </a>
                    <a href="?filter=5y" class="btn <?= $filter === '5y' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        5 Anni
                    </a>
                    <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                        Tutto
                    </a>
                </div>
            </div>
            
            <div class="mt-2">
                <small class="text-muted">
                    <span class="fw-bold">Periodo selezionato:</span> <?= getPeriodText($filter) ?>
                    <span class="ms-3">
                        <?= date('d/m/Y', strtotime($startDate)) ?> - <?= date('d/m/Y', strtotime($endDate)) ?>
                    </span>
                </small>
            </div>
        </div>
    </div>

    <!-- Statistiche Riepilogative -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Riepilogo periodo da <?= date('d/m/Y', strtotime($startDate)) ?> a <?= date('d/m/Y', strtotime($endDate)) ?></h4>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3>€<?= number_format($stats['totalRevenue'], 2) ?></h3>
                            <p class="mb-0">Entrate Totali</p>
                            <small class="text-light"><?= $stats['totalPayments'] ?> pagamenti</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3>€<?= number_format($stats['totalExpenses'], 2) ?></h3>
                            <p class="mb-0">Spese Totali</p>
                            <small class="text-light"><?= $stats['totalMaintenances'] ?> manutenzioni</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, <?= $stats['netProfit'] >= 0 ? '#17a2b8, #6f42c1' : '#6c757d, #495057' ?>);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3>€<?= number_format($stats['netProfit'], 2) ?></h3>
                            <p class="mb-0"><?= $stats['netProfit'] >= 0 ? 'Utile Netto' : 'Perdita Netta' ?></p>
                            <small class="text-light"><?= $stats['netProfit'] >= 0 ? '+' : '' ?><?= number_format(($stats['totalRevenue'] > 0 ? ($stats['netProfit'] / $stats['totalRevenue']) * 100 : 0), 1) ?>%</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #6f42c1 0%, #e83e8c 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3>€<?= $stats['totalRevenue'] > 0 ? number_format($stats['totalRevenue'] / $stats['totalPayments'], 2) : '0.00' ?></h3>
                            <p class="mb-0">Ricavo Medio</p>
                            <small class="text-light">per transazione</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafico Comparativo -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title">Entrate e Spese - <?= getPeriodText($filter) ?></h5>
            <div style="height: 400px; position: relative;">
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Dettagli -->
    <div class="row">
        <!-- Entrate per Abbonamento -->
        <?php if (!empty($stats['membershipRevenue'])): ?>
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        Entrate per Tipo Abbonamento
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Abbonamento</th>
                                    <th>Vendite</th>
                                    <th>Ricavo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($stats['membershipRevenue'] as $membership): ?>
                                <tr>
                                    <td><?= htmlspecialchars($membership['membership_name']) ?></td>
                                    <td><span class="badge bg-info"><?= $membership['sales_count'] ?></span></td>
                                    <td><strong class="text-success">€<?= number_format($membership['revenue'], 2) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top Spese Attrezzature -->
        <?php if (!empty($stats['topExpenses'])): ?>
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        Top Spese Manutenzioni
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Attrezzatura</th>
                                    <th>Interventi</th>
                                    <th>Costo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($stats['topExpenses'] as $expense): ?>
                                <tr>
                                    <td><?= htmlspecialchars($expense['equipment_name']) ?></td>
                                    <td><span class="badge bg-warning"><?= $expense['maintenance_count'] ?></span></td>
                                    <td><strong class="text-danger">€<?= number_format($expense['total_cost'], 2) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Top 5 Clienti che Spendono di Più -->
        <?php if (!empty($stats['topCustomers'])): ?>
        <div class="col-lg-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">
                        Top 5 Clienti
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Pagamenti</th>
                                    <th>Spesa Totale</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($stats['topCustomers'] as $index => $customer): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($customer['firstName'] . ' ' . $customer['lastName']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($customer['email']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $customer['payment_count'] ?></span>
                                        <br><small class="text-muted">€<?= number_format($customer['avg_payment'], 2) ?> media</small>
                                    </td>
                                    <td>
                                        <strong class="text-success">€<?= number_format($customer['total_spent'], 2) ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Messaggio se nessun dato -->
    <?php if (empty($dailyRevenue) && empty($dailyExpenses)): ?>
    <div class="card shadow-sm">
        <div class="card-body text-center py-5">
            <h5 class="text-muted">Nessun dato finanziario</h5>
            <p class="text-muted">Non ci sono entrate o spese registrate per il periodo selezionato (<?= getPeriodText($filter) ?>).</p>
            <p class="text-muted">Prova a selezionare un periodo diverso o aggiungi dei dati.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
// Dati per i grafici
const chartDates = <?= json_encode($chartDates) ?>;
const chartRevenue = <?= json_encode($chartRevenue) ?>;
const chartExpenses = <?= json_encode($chartExpenses) ?>;

// Configurazione comune per i grafici
const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        },
        tooltip: {
            backgroundColor: 'rgba(255,255,255,0.95)',
            titleColor: '#2c3e50',
            bodyColor: '#2c3e50',
            borderColor: '#bdc3c7',
            borderWidth: 1,
            cornerRadius: 8,
            callbacks: {
                label: function(context) {
                    return context.dataset.label + ': €' + parseFloat(context.parsed.y).toFixed(2);
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function(value) {
                    return '€' + value;
                }
            }
        }
    },
    elements: {
        point: {
            radius: 4,
            hoverRadius: 6
        },
        line: {
            tension: 0.2
        }
    }
};

// Grafico Comparativo
new Chart(document.getElementById('comparisonChart'), {
    type: 'line',
    data: {
        labels: chartDates,
        datasets: [{
            label: 'Entrate',
            data: chartRevenue,
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            fill: true
        }, {
            label: 'Spese',
            data: chartExpenses,
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            fill: true
        }]
    },
    options: {
        ...commonOptions,
        plugins: {
            ...commonOptions.plugins,
            legend: {
                position: 'top',
            }
        }
    }
});
</script>
</body>
</html>