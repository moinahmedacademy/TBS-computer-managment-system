<?php
require_once __DIR__ . '/db.php';

function getSetting($key) {
    $row = db()->fetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $row ? $row['setting_value'] : '';
}

function getGrade($percentage) {
    $p = (float)$percentage;
    if ($p >= 90) return 'A+';
    if ($p >= 80) return 'A';
    if ($p >= 70) return 'B';
    if ($p >= 60) return 'C';
    if ($p >= 50) return 'D';
    return 'F';
}

function getGradeClass($grade) {
    return match($grade) {
        'A+' => 'success',
        'A'  => 'success',
        'B'  => 'info',
        'C'  => 'warning',
        'D'  => 'warning',
        'F'  => 'danger',
        default => 'secondary'
    };
}

function getAttendancePercentage($studentId, $courseId, $month = null, $year = null) {
    $sql = "SELECT COUNT(*) as total,
            SUM(status='present') as present,
            SUM(status='absent') as absent,
            SUM(status='leave') as onleave
            FROM attendance WHERE student_id=? AND course_id=?";
    $params = [$studentId, $courseId];

    if ($month && $year) {
        $sql .= " AND MONTH(date)=? AND YEAR(date)=?";
        $params[] = $month;
        $params[] = $year;
    }

    $row = db()->fetchOne($sql, $params);
    if (!$row || $row['total'] == 0) return ['total'=>0,'present'=>0,'absent'=>0,'leave'=>0,'percentage'=>0];

    $percentage = ($row['present'] / $row['total']) * 100;
    return [
        'total'      => $row['total'],
        'present'    => $row['present'] ?? 0,
        'absent'     => $row['absent'] ?? 0,
        'leave'      => $row['onleave'] ?? 0,
        'percentage' => round($percentage, 2)
    ];
}

function uploadFile($file, $destination, $allowedTypes = ['pdf','doc','docx','ppt','pptx','jpg','jpeg','png','gif']) {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'File upload error: ' . $file['error']];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return ['error' => 'File type not allowed. Allowed: ' . implode(', ', $allowedTypes)];
    }

    $maxSize = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $maxSize) {
        return ['error' => 'File size exceeds 50MB limit.'];
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $uniqueName = uniqid('file_', true) . '.' . $ext;
    $fullPath   = $destination . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['error' => 'Failed to save uploaded file.'];
    }

    return [
        'success'   => true,
        'file_name' => $file['name'],
        'file_path' => $fullPath,
        'file_type' => $ext,
        'file_size' => $file['size'],
        'unique_name' => $uniqueName,
    ];
}

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024, 1) . ' KB';
    return round($bytes/1048576, 1) . ' MB';
}

function formatDate($date, $format = 'd M Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

function sanitize($value) {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function generateRollNumber($courseCode) {
    $year = date('Y');
    $count = db()->fetchOne("SELECT COUNT(*) as cnt FROM students WHERE roll_number LIKE ?", [$courseCode . $year . '%']);
    $num = ($count['cnt'] ?? 0) + 1;
    return $courseCode . $year . str_pad($num, 3, '0', STR_PAD_LEFT);
}

function sendWhatsAppMessage($phone, $message, $type = 'custom', $sentBy = null) {
    $apiKey = getSetting('whatsapp_api_key');
    $instanceId = getSetting('whatsapp_instance_id');

    // Clean phone number
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === '0') {
        $phone = '92' . substr($phone, 1);
    }

    $status = 'pending';

    if ($apiKey && $instanceId) {
        // Using CallMeBot or UltraMsg API (configurable)
        $url = "https://api.ultramsg.com/{$instanceId}/messages/chat";
        $data = http_build_query([
            'token'  => $apiKey,
            'to'     => $phone,
            'body'   => $message
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $status = ($httpCode === 200) ? 'sent' : 'failed';
    }

    // Generate WhatsApp URL as fallback
    $encodedMsg = urlencode($message);
    $waUrl = "https://wa.me/{$phone}?text={$encodedMsg}";

    // Log the message
    db()->execute(
        "INSERT INTO whatsapp_logs (sent_by, phone, message, message_type, status) VALUES (?,?,?,?,?)",
        [$sentBy, $phone, $message, $type, $status]
    );

    return ['status' => $status, 'wa_url' => $waUrl, 'phone' => $phone];
}

function getMonthName($month) {
    return date('F', mktime(0, 0, 0, $month, 1));
}

function breadcrumb($items) {
    echo '<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0">';
    $last = array_key_last($items);
    foreach ($items as $i => $item) {
        if ($i === $last) {
            echo '<li class="breadcrumb-item active">' . sanitize($item['label']) . '</li>';
        } else {
            echo '<li class="breadcrumb-item"><a href="' . $item['url'] . '">' . sanitize($item['label']) . '</a></li>';
        }
    }
    echo '</ol></nav>';
}

function flashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function showFlash() {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $icon = match($f['type']) {
            'success' => 'check-circle',
            'danger'  => 'x-circle',
            'warning' => 'exclamation-triangle',
            default   => 'info-circle'
        };
        echo "<div class='alert alert-{$f['type']} alert-dismissible fade show d-flex align-items-center gap-2' role='alert'>
            <i class='bi bi-{$icon}'></i>
            <span>{$f['message']}</span>
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

function calculatePositions($testId) {
    $results = db()->fetchAll(
        "SELECT id, obtained_marks FROM test_results WHERE test_id = ? ORDER BY obtained_marks DESC",
        [$testId]
    );
    foreach ($results as $pos => $r) {
        db()->execute(
            "UPDATE test_results SET position = ? WHERE id = ?",
            [$pos + 1, $r['id']]
        );
    }
}

function generateResultCard($studentId, $courseId, $month = null, $year = null) {
    $student = db()->fetchOne(
        "SELECT s.*, c.name as course_name, c.code as course_code, u.email
         FROM students s
         JOIN courses c ON s.course_id = c.id
         JOIN users u ON s.user_id = u.id
         WHERE s.id = ? AND s.course_id = ?",
        [$studentId, $courseId]
    );
    if (!$student) return null;

    $attendance = getAttendancePercentage($studentId, $courseId, $month, $year);

    $testSql = "SELECT tr.*, t.name as test_name, t.total_marks, t.test_type
                FROM test_results tr
                JOIN tests t ON tr.test_id = t.id
                WHERE tr.student_id = ? AND t.course_id = ?";
    $params = [$studentId, $courseId];
    if ($month && $year) {
        $testSql .= " AND MONTH(t.date)=? AND YEAR(t.date)=?";
        $params[] = $month;
        $params[] = $year;
    }
    $results = db()->fetchAll($testSql, $params);

    $totalMarks = 0;
    $totalObtained = 0;
    foreach ($results as $r) {
        $totalMarks   += $r['total_marks'];
        $totalObtained += $r['obtained_marks'];
    }
    $overallPercentage = $totalMarks > 0 ? round(($totalObtained / $totalMarks) * 100, 2) : 0;

    return [
        'student'    => $student,
        'attendance' => $attendance,
        'results'    => $results,
        'total_marks'=> $totalMarks,
        'obtained'   => $totalObtained,
        'percentage' => $overallPercentage,
        'grade'      => getGrade($overallPercentage),
    ];
}
