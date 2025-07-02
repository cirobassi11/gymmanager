<?php
require_once 'config.php';
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Controllo accesso trainer
if (!isset($_SESSION['userID'], $_SESSION['role']) || $_SESSION['role'] !== 'trainer') {
    header('Location: login.php');
    exit();
}

$trainerID = $_SESSION['userID'];

// Recupera i corsi assegnati al trainer
$stmt = $conn->prepare("
    SELECT c.courseID, c.name, c.description, c.price, c.maxParticipants, c.startDate, c.finishDate,
           COUNT(e.customerID) as enrolled_count
    FROM COURSE c
    JOIN teaching t ON c.courseID = t.courseID
    LEFT JOIN enrollment e ON c.courseID = e.courseID
    WHERE t.trainerID = ?
    GROUP BY c.courseID, c.name, c.description, c.price, c.maxParticipants, c.startDate, c.finishDate
    ORDER BY c.startDate DESC
");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Recupera l'andamento delle iscrizioni per ogni corso nel tempo
$enrollmentData = [];
$courseColors = ['#28a745', '#17a2b8', '#fd7e14', '#e83e8c', '#6f42c1', '#20c997', '#dc3545', '#ffc107'];

foreach ($courses as $index => $course) {
    $stmt = $conn->prepare("
        SELECT DATE(enrollmentDate) as enroll_date,
               COUNT(*) as daily_enrollments
        FROM enrollment e
        WHERE e.courseID = ?
        GROUP BY DATE(enrollmentDate)
        ORDER BY enroll_date ASC
    ");
    $stmt->bind_param('i', $course['courseID']);
    $stmt->execute();
    $courseEnrollments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($courseEnrollments)) {
        // Calcola il cumulativo in PHP
        $cumulative = 0;
        $processedData = [];
        foreach ($courseEnrollments as $enrollment) {
            $cumulative += (int)$enrollment['daily_enrollments'];
            $processedData[] = [
                'enroll_date' => $enrollment['enroll_date'],
                'daily_enrollments' => $enrollment['daily_enrollments'],
                'cumulative_enrollments' => $cumulative
            ];
        }
        
        $enrollmentData[] = [
            'courseID' => $course['courseID'],
            'courseName' => $course['name'],
            'data' => $processedData,
            'color' => $courseColors[$index % count($courseColors)],
            'maxParticipants' => $course['maxParticipants']
        ];
    }
}

// Statistiche trainer
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM teaching WHERE trainerID = ?");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$totalCourses = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("
    SELECT COUNT(*) as active 
    FROM COURSE c
    JOIN teaching t ON c.courseID = t.courseID
    WHERE t.trainerID = ? AND c.startDate <= CURDATE() AND c.finishDate >= CURDATE()
");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$activeCourses = $stmt->get_result()->fetch_assoc()['active'];

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT e.customerID) as total_students
    FROM enrollment e
    JOIN teaching t ON e.courseID = t.courseID
    WHERE t.trainerID = ?
");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$totalStudents = $stmt->get_result()->fetch_assoc()['total_students'];

// Informazioni trainer
$stmt = $conn->prepare("SELECT firstName, lastName, specialization FROM USER WHERE userID = ?");
$stmt->bind_param('i', $trainerID);
$stmt->execute();
$trainerInfo = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>I Tuoi Corsi</title>
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
                <div>
                    <h2>I Tuoi Corsi</h2>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i> Torna alla Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Statistiche -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <h4>Statistiche Corsi</h4>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $totalCourses ?></h3>
                            <p class="mb-0">Corsi Assegnati</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $activeCourses ?></h3>
                            <p class="mb-0">Corsi Attivi</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white h-100" style="background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);">
                        <div class="card-body text-center d-flex flex-column justify-content-center">
                            <h3><?= $totalStudents ?></h3>
                            <p class="mb-0">Clienti Totali</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Grafico Andamento Iscrizioni -->
    <?php if (!empty($enrollmentData)): ?>
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Andamento Iscrizioni ai Corsi</h4>
                
                <!-- Filtri temporali -->
                <div class="btn-group" role="group" aria-label="Filtri temporali">
                    <button type="button" class="btn btn-primary" onclick="filterChart('1w')" id="btn-1w">
                        1 Settimana
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="filterChart('1m')" id="btn-1m">
                        1 Mese
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="filterChart('1y')" id="btn-1y">
                        1 Anno
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="filterChart('5y')" id="btn-5y">
                        5 Anni
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="filterChart('all')" id="btn-all">
                        Tutto
                    </button>
                </div>
            </div>
            
            <div style="height: 400px; position: relative;">
                <canvas id="enrollmentChart"></canvas>
            </div>
            
            <div class="mt-3">
                <small class="text-muted">
                    Il grafico mostra l'accumulo progressivo delle iscrizioni nel tempo per ogni corso.
                    <span id="chart-period-info" class="fw-bold">Periodo: Ultima settimana</span>
                </small>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Lista corsi -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h4>Dettaglio Corsi</h4>
            <?php if (!empty($courses)): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Prezzo</th>
                                <th>Partecipanti</th>
                                <th>Data Inizio</th>
                                <th>Data Fine</th>
                                <th>Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($courses as $course): ?>
                                <?php
                                $today = new DateTime();
                                $startDate = new DateTime($course['startDate']);
                                $finishDate = new DateTime($course['finishDate']);
                                
                                if ($startDate > $today) {
                                    $status = 'In attesa';
                                    $statusClass = 'badge bg-warning';
                                } elseif ($finishDate >= $today) {
                                    $status = 'In corso';
                                    $statusClass = 'badge bg-success';
                                } else {
                                    $status = 'Completato';
                                    $statusClass = 'badge bg-primary';
                                }
                                
                                // Percentuale riempimento
                                $fillPercentage = ($course['enrolled_count'] / $course['maxParticipants']) * 100;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($course['name']) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars(substr($course['description'], 0, 60)) ?>
                                            <?= strlen($course['description']) > 60 ? '...' : '' ?>
                                        </small>
                                    </td>
                                    <td>€<?= number_format($course['price'], 2) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="<?= $course['enrolled_count'] >= $course['maxParticipants'] ? 'text-danger fw-bold' : 'text-success' ?> me-2">
                                                <?= $course['enrolled_count'] ?>/<?= $course['maxParticipants'] ?>
                                            </span>
                                            <div class="progress flex-fill" style="height: 6px; max-width: 60px;">
                                                <div class="progress-bar <?= $fillPercentage >= 100 ? 'bg-danger' : ($fillPercentage >= 80 ? 'bg-warning' : 'bg-success') ?>" 
                                                     style="width: <?= min($fillPercentage, 100) ?>%"></div>
                                            </div>
                                        </div>
                                        <small class="text-muted"><?= number_format($fillPercentage, 1) ?>% pieno</small>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($course['startDate'])) ?></td>
                                    <td><?= date('d/m/Y', strtotime($course['finishDate'])) ?></td>
                                    <td><span class="<?= $statusClass ?>"><?= $status ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <h5 class="text-muted">Nessun corso assegnato</h5>
                    <p class="text-muted">Al momento non hai corsi assegnati. Contatta l'amministratore per richiedere l'assegnazione di corsi.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<?php if (!empty($enrollmentData)): ?>
<script>
// Debug: mostra i dati ricevuti
console.log('Dati enrollment ricevuti:', <?= json_encode($enrollmentData) ?>);

// Prepara i dati per il grafico
const enrollmentData = <?= json_encode($enrollmentData) ?>;
let chart;
let allDatasets = [];

// Funzione per convertire stringa data in oggetto Date
function parseDate(dateString) {
    return new Date(dateString + 'T00:00:00');
}

// Funzione per calcolare la data limite basata sul filtro
function getDateLimit(filter) {
    const now = new Date();
    switch(filter) {
        case '1w':
            return new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
        case '1m':
            return new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
        case '1y':
            return new Date(now.getTime() - 365 * 24 * 60 * 60 * 1000);
        case '5y':
            return new Date(now.getTime() - 5 * 365 * 24 * 60 * 60 * 1000);
        case 'all':
        default:
            return null;
    }
}

// Funzione per aggiornare il testo informativo
function updatePeriodInfo(filter) {
    const periodTexts = {
        '1w': 'Ultima settimana',
        '1m': 'Ultimo mese',
        '1y': 'Ultimo anno',
        '5y': 'Ultimi 5 anni',
        'all': 'Tutti i dati disponibili'
    };
    
    document.getElementById('chart-period-info').textContent = 'Periodo: ' + periodTexts[filter];
}

// Funzione per aggiornare gli stili dei pulsanti
function updateButtonStyles(activeFilter) {
    document.querySelectorAll('.btn-group button').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-primary');
    });
    
    const activeBtn = document.getElementById('btn-' + activeFilter);
    if (activeBtn) {
        activeBtn.classList.remove('btn-outline-primary');
        activeBtn.classList.add('btn-primary');
    }
}

// Crea i dataset per ogni corso
function createDatasets() {
    return enrollmentData.map(course => {
        // Ordina i dati per data
        const sortedData = course.data.sort((a, b) => 
            parseDate(a.enroll_date).getTime() - parseDate(b.enroll_date).getTime()
        );
        
        const data = sortedData.map(point => {
            const dateObj = parseDate(point.enroll_date);
            return {
                x: dateObj.getTime(),
                y: parseInt(point.cumulative_enrollments),
                dateLabel: point.enroll_date
            };
        });
        
        console.log(`Dataset ${course.courseName}:`, data);
        
        return {
            label: course.courseName,
            data: data,
            originalData: data,
            borderColor: course.color,
            backgroundColor: course.color + '20',
            fill: false,
            tension: 0.2,
            pointRadius: 4,
            pointHoverRadius: 6,
            borderWidth: 2
        };
    });
}

// Funzione principale per filtrare il grafico
function filterChart(period) {
    console.log('Filtering chart for period:', period);
    
    updateButtonStyles(period);
    updatePeriodInfo(period);
    
    const dateLimit = getDateLimit(period);
    console.log('Date limit:', dateLimit);
    
    const filteredDatasets = allDatasets.map(dataset => {
        let filteredData = dataset.originalData;
        
        if (dateLimit) {
            filteredData = dataset.originalData.filter(point => {
                const pointDate = new Date(point.x);
                return pointDate >= dateLimit;
            });
        }
        
        console.log(`Filtered data for ${dataset.label}:`, filteredData);
        
        return {
            ...dataset,
            data: filteredData
        };
    });
    
    chart.data.datasets = filteredDatasets;
    chart.update('active');
}

// Configurazione del grafico
const config = {
    type: 'line',
    data: {
        datasets: []
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: 'Crescita Iscrizioni nel Tempo'
            },
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 20
                }
            },
            tooltip: {
                backgroundColor: 'rgba(255,255,255,0.95)',
                titleColor: '#2c3e50',
                bodyColor: '#2c3e50',
                borderColor: '#bdc3c7',
                borderWidth: 1,
                cornerRadius: 8,
                callbacks: {
                    title: function(context) {
                        const timestamp = context[0].parsed.x;
                        return 'Data: ' + new Date(timestamp).toLocaleDateString('it-IT');
                    },
                    label: function(context) {
                        return context.dataset.label + ': ' + context.parsed.y + ' iscritti';
                    }
                }
            }
        },
        scales: {
            x: {
                type: 'linear',
                title: {
                    display: true,
                    text: 'Data Iscrizione'
                },
                grid: {
                    color: 'rgba(0,0,0,0.1)'
                },
                ticks: {
                    callback: function(value) {
                        return new Date(value).toLocaleDateString('it-IT', {
                            month: 'short',
                            day: 'numeric'
                        });
                    },
                    maxTicksLimit: 8
                }
            },
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Numero Iscritti (Cumulativo)'
                },
                ticks: {
                    stepSize: 1,
                    precision: 0
                },
                grid: {
                    color: 'rgba(0,0,0,0.1)'
                }
            }
        },
        interaction: {
            intersect: false,
            mode: 'index'
        }
    }
};

// Inizializza il grafico
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing chart...');
    
    if (enrollmentData.length === 0) {
        console.log('Nessun dato di enrollment disponibile');
        return;
    }
    
    allDatasets = createDatasets();
    console.log('All datasets created:', allDatasets);
    
    chart = new Chart(document.getElementById('enrollmentChart'), config);
    
    // Carica tutti i dati inizialmente
    filterChart('all');
});
</script>
<?php endif; ?>
</body>
</html>