<?php
// ============================================
// ST JUDE BUKOMANSIMBI PRIMARY SCHOOL
// COMPLETE SCHOOL MANAGEMENT SYSTEM
// ============================================

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
$host = 'localhost';
$dbname = 'school_management';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ============================================
// LOGIN HANDLER
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['user_name'] = 'Administrator';
        $_SESSION['username'] = 'admin';
        header('Location: ?page=dashboard');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['username'] = $user['username'];
        header('Location: ?page=dashboard');
        exit;
    } else {
        $login_error = "Invalid username or password. Try: admin / admin123";
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

// Page router
$page = $_GET['page'] ?? 'login';
if (!isset($_SESSION['user_id']) && $page !== 'login') {
    $page = 'login';
}

// ============================================
// BULK ATTENDANCE HANDLER - ARRIVAL & DEPARTURE
// ============================================
if ($page === 'attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Single student attendance
    if (isset($_POST['mark_attendance'])) {
        $student_id = $_POST['student_id'];
        $date = $_POST['date'];
        $status = $_POST['status'];
        $attendance_type = $_POST['attendance_type'] ?? 'arrival';
        $send_whatsapp = isset($_POST['send_whatsapp']) ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, attendance_type) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, attendance_type = ?");
        $stmt->execute([$student_id, $date, $status, $attendance_type, $status, $attendance_type]);
        
        $s = $pdo->prepare("SELECT name, phone FROM students WHERE id = ?");
        $s->execute([$student_id]);
        $student = $s->fetch(PDO::FETCH_ASSOC);
        
        if ($send_whatsapp && $student && $student['phone']) {
            $phone = preg_replace('/[^0-9]/', '', $student['phone']);
            if (substr($phone, 0, 1) === '0') {
                $phone = '256' . substr($phone, 1);
            }
            if (substr($phone, 0, 3) !== '256' && strlen($phone) < 10) {
                $phone = '256' . $phone;
            }
            
            if ($attendance_type === 'arrival') {
                $status_message = 'arrived at school';
                $emoji = '🌅';
                $title = 'Arrival Notification';
                $time_period = 'morning';
            } else {
                $status_message = 'has departed from school';
                $emoji = '🌙';
                $title = 'Departure Notification';
                $time_period = 'evening';
            }
            
            $message = "🏫 *St Jude Bukomansimbi Primary School*\n\n";
            $message .= "*📋 {$title}*\n\n";
            $message .= "Dear Parent/Guardian,\n\n";
            $message .= "Your child *{$student['name']}* has {$status_message} on *" . date('F j, Y') . "* at *" . date('g:i A') . "* ({$time_period}).\n\n";
            $message .= "📌 Status: {$emoji} {$status}\n\n";
            $message .= "Thank you for your continued support.\n\n";
            $message .= "📱 St Jude Bukomansimbi Primary School";
            
            $whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($message);
            
            $_SESSION['whatsapp_link'] = $whatsapp_url;
            $_SESSION['whatsapp_student'] = $student['name'];
            $_SESSION['whatsapp_phone'] = $phone;
            $_SESSION['whatsapp_message'] = $message;
            $attendance_success = "Attendance marked! Click the WhatsApp button below to send notification.";
        } else {
            $attendance_success = "Attendance marked!";
        }
    }
    
    // BULK ATTENDANCE - Arrival or Departure
    if (isset($_POST['bulk_attendance'])) {
        $date = $_POST['date'];
        $status = $_POST['bulk_status'];
        $attendance_type = $_POST['attendance_type'] ?? 'arrival';
        $student_ids = $_POST['student_ids'] ?? [];
        $send_whatsapp = isset($_POST['send_whatsapp_bulk']) ? 1 : 0;
        
        if (empty($student_ids)) {
            $attendance_error = "Please select at least one student!";
        } else {
            $success_count = 0;
            $whatsapp_links = [];
            
            foreach ($student_ids as $student_id) {
                $stmt = $pdo->prepare("INSERT INTO attendance (student_id, date, status, attendance_type) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, attendance_type = ?");
                $stmt->execute([$student_id, $date, $status, $attendance_type, $status, $attendance_type]);
                $success_count++;
                
                if ($send_whatsapp) {
                    $s = $pdo->prepare("SELECT name, phone FROM students WHERE id = ?");
                    $s->execute([$student_id]);
                    $student = $s->fetch(PDO::FETCH_ASSOC);
                    
                    if ($student && $student['phone']) {
                        $phone = preg_replace('/[^0-9]/', '', $student['phone']);
                        if (substr($phone, 0, 1) === '0') {
                            $phone = '256' . substr($phone, 1);
                        }
                        if (substr($phone, 0, 3) !== '256' && strlen($phone) < 10) {
                            $phone = '256' . $phone;
                        }
                        
                        if ($attendance_type === 'arrival') {
                            $status_message = 'arrived at school';
                            $emoji = '🌅';
                            $title = 'Arrival Notification';
                            $time_period = 'morning';
                        } else {
                            $status_message = 'has departed from school';
                            $emoji = '🌙';
                            $title = 'Departure Notification';
                            $time_period = 'evening';
                        }
                        
                        $message = "🏫 *St Jude Bukomansimbi Primary School*\n\n";
                        $message .= "*📋 {$title}*\n\n";
                        $message .= "Dear Parent/Guardian,\n\n";
                        $message .= "Your child *{$student['name']}* has {$status_message} on *" . date('F j, Y') . "* at *" . date('g:i A') . "* ({$time_period}).\n\n";
                        $message .= "📌 Status: {$emoji} {$status}\n\n";
                        $message .= "Thank you for your continued support.\n\n";
                        $message .= "📱 St Jude Bukomansimbi Primary School";
                        
                        $whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($message);
                        $whatsapp_links[] = [
                            'name' => $student['name'],
                            'url' => $whatsapp_url,
                            'phone' => $phone,
                            'message' => $message
                        ];
                    }
                }
            }
            
            if ($send_whatsapp && !empty($whatsapp_links)) {
                $_SESSION['bulk_whatsapp_links'] = $whatsapp_links;
                $_SESSION['bulk_count'] = count($whatsapp_links);
                $attendance_success = "{$success_count} students marked {$status} for " . ($attendance_type === 'arrival' ? 'Arrival' : 'Departure') . "! WhatsApp notifications ready for " . count($whatsapp_links) . " students.";
            } else {
                $attendance_success = "{$success_count} students marked {$status} for " . ($attendance_type === 'arrival' ? 'Arrival' : 'Departure') . " successfully!";
            }
        }
    }
}

// ============================================
// STUDENT REGISTRATION
// ============================================
if ($page === 'register_student' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $new_filename = time() . '_' . uniqid() . '.' . $ext;
            $upload_path = 'uploads/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_path . $new_filename);
            $image_path = $upload_path . $new_filename;
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO students (name, email, class, phone, image, admission_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['name'], $_POST['email'], $_POST['class'], $_POST['phone'], $image_path, date('Y-m-d')]);
    $success = "Student registered successfully!";
}

// ============================================
// TEACHER REGISTRATION
// ============================================
if ($page === 'register_teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $stmt = $pdo->prepare("INSERT INTO teachers (name, email, subject, phone, qualification, joining_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['name'],
        $_POST['email'],
        $_POST['subject'],
        $_POST['phone'],
        $_POST['qualification'],
        date('Y-m-d')
    ]);
    $success = "Teacher registered successfully!";
}

// ============================================
// FEES HANDLER
// ============================================
if ($page === 'fees' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee'])) {
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $paid = isset($_POST['paid']) ? 1 : 0;
    $payment_date = $paid ? date('Y-m-d') : NULL;
    
    if ($month < 1 || $month > 12) {
        $fee_error = "Error: Month must be between 1 and 12!";
    } elseif ($year < 2000 || $year > 2100) {
        $fee_error = "Error: Please enter a valid year (2000-2100)!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO fees (student_id, amount, month, year, paid, payment_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $amount, $month, $year, $paid, $payment_date]);
            $fee_success = "Fee record added successfully!";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $fee_error = "Error: A fee record for this student, month, and year already exists!";
            } else {
                $fee_error = "Error: " . $e->getMessage();
            }
        }
    }
}

// ============================================
// RESULTS HANDLER
// ============================================
if ($page === 'add_result' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_result'])) {
    $stmt = $pdo->prepare("INSERT INTO results (student_id, subject, marks, exam_type, term, year) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['student_id'],
        $_POST['subject'],
        $_POST['marks'],
        $_POST['exam_type'],
        $_POST['term'],
        date('Y')
    ]);
    $result_success = "Result added successfully!";
}

// ============================================
// BULK MARKS ENTRY
// ============================================
if ($page === 'add_result' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bulk_marks'])) {
    $student_id = $_POST['student_id'];
    $exam_type = $_POST['exam_type'];
    $term = $_POST['term'];
    $subjects = $_POST['subjects'] ?? [];
    $marks = $_POST['marks'] ?? [];
    
    foreach ($subjects as $index => $subject) {
        if (!empty($subject) && isset($marks[$index]) && $marks[$index] !== '') {
            $stmt = $pdo->prepare("INSERT INTO results (student_id, subject, marks, exam_type, term, year) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $subject, $marks[$index], $exam_type, $term, date('Y')]);
        }
    }
    $result_success = "All marks added successfully!";
}

// ============================================
// DELETE STUDENT HANDLER
// ============================================
if ($page === 'view_student' && isset($_GET['delete'])) {
    $student_id = (int)$_GET['delete'];
    
    $check = $pdo->prepare("SELECT id, image FROM students WHERE id = ?");
    $check->execute([$student_id]);
    $student = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        if (!empty($student['image']) && file_exists($student['image'])) {
            unlink($student['image']);
        }
        
        $delete = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $delete->execute([$student_id]);
        
        $_SESSION['notification'] = "Student deleted successfully!";
        header('Location: ?page=view_student');
        exit;
    } else {
        $_SESSION['notification'] = "Student not found!";
        header('Location: ?page=view_student');
        exit;
    }
}

// ============================================
// PRINT STUDENT REPORT
// ============================================
if ($page === 'print_student_report' && isset($_GET['id'])) {
    $student_id = $_GET['id'];
    $term = $_GET['term'] ?? 'all';
    $exam_type = $_GET['exam_type'] ?? 'all';
    
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($student) {
        $query = "SELECT * FROM results WHERE student_id = ?";
        $params = [$student_id];
        
        if ($term !== 'all') {
            $query .= " AND term = ?";
            $params[] = $term;
        }
        if ($exam_type !== 'all') {
            $query .= " AND exam_type = ?";
            $params[] = $exam_type;
        }
        $query .= " ORDER BY term, subject";
        
        $results_stmt = $pdo->prepare($query);
        $results_stmt->execute($params);
        $results = $results_stmt->fetchAll();
        
        $fee_stmt = $pdo->prepare("SELECT SUM(amount) as total, SUM(CASE WHEN paid = 1 THEN amount ELSE 0 END) as paid FROM fees WHERE student_id = ?");
        $fee_stmt->execute([$student_id]);
        $fee_data = $fee_stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Student Report - <?= htmlspecialchars($student['name']) ?></title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Inter', sans-serif; background: #f0f4f8; padding: 30px; }
                .report-card { max-width: 1000px; margin: 0 auto; background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 3px solid #f7971e; padding-bottom: 20px; margin-bottom: 25px; }
                .header .school-name { font-size: 2rem; font-weight: 800; color: #1a1a2e; }
                .header .school-sub { color: #718096; font-size: 0.95rem; }
                .header .report-type { background: linear-gradient(135deg, #f7971e, #ffd200); display: inline-block; padding: 8px 30px; border-radius: 30px; font-weight: 700; font-size: 1.1rem; color: #1a1a2e; margin-top: 10px; }
                .student-info { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #f7fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px; }
                .student-info .info-item { display: flex; flex-direction: column; }
                .student-info .info-item .label { color: #718096; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
                .student-info .info-item .value { font-size: 1.1rem; font-weight: 600; color: #1a1a2e; }
                .student-photo { text-align: right; }
                .student-photo img { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #ffd200; }
                .student-photo .placeholder { width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #f7971e, #ffd200); display: inline-flex; align-items: center; justify-content: center; color: #1a1a2e; font-size: 2rem; font-weight: 700; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                th { background: linear-gradient(135deg, #1a1a2e, #16213e); color: white; padding: 12px 15px; text-align: left; font-weight: 600; }
                td { padding: 10px 15px; border-bottom: 1px solid #e8ecf0; }
                tr:hover { background: #f7fafc; }
                .grade { display: inline-block; padding: 4px 14px; border-radius: 20px; font-weight: 700; font-size: 0.8rem; }
                .grade-A { background: #c6f6d5; color: #276749; }
                .grade-B { background: #bee3f8; color: #2a4365; }
                .grade-C { background: #fefcbf; color: #975a16; }
                .grade-D { background: #fed7d7; color: #9b2c2c; }
                .grade-F { background: #fed7d7; color: #9b2c2c; }
                .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-top: 25px; padding-top: 25px; border-top: 2px solid #e8ecf0; }
                .summary-item { text-align: center; background: #f7fafc; padding: 15px; border-radius: 10px; }
                .summary-item .number { font-size: 1.8rem; font-weight: 800; color: #f7971e; }
                .summary-item .label { color: #718096; font-size: 0.75rem; font-weight: 500; }
                .print-btn { display: inline-block; padding: 14px 30px; background: linear-gradient(135deg, #f7971e, #ffd200); color: #1a1a2e; border: none; border-radius: 10px; font-weight: 700; font-size: 1rem; cursor: pointer; margin-top: 20px; transition: 0.3s; }
                .print-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(247,151,30,0.3); }
                .back-btn { display: inline-block; padding: 14px 30px; background: #e2e8f0; color: #1a1a2e; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; text-decoration: none; margin-right: 10px; transition: 0.3s; }
                .back-btn:hover { background: #cbd5e0; }
                .fee-section { background: #f7fafc; padding: 15px; border-radius: 10px; margin-top: 20px; }
                .fee-section .fee-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e8ecf0; }
                .fee-section .fee-item:last-child { border-bottom: none; }
                .remarks { margin-top: 20px; padding: 15px; background: #fff9e6; border-radius: 10px; border-left: 4px solid #f7971e; }
                .signature-section { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e8ecf0; }
                .signature-section .signature-line { border-bottom: 1px solid #1a1a2e; width: 80%; margin-top: 5px; }
                .exam-badge { display: inline-block; padding: 2px 12px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; background: #bee3f8; color: #2a4365; }
                .exam-badge.midterm { background: #bee3f8; color: #2a4365; }
                .exam-badge.endterm { background: #c6f6d5; color: #276749; }
                @media print { body { background: white; padding: 0; } .report-card { box-shadow: none; padding: 20px; } .print-btn, .back-btn { display: none; } }
                @media (max-width: 600px) { .student-info { grid-template-columns: 1fr; } .summary { grid-template-columns: 1fr 1fr; } .report-card { padding: 20px; } .signature-section { grid-template-columns: 1fr; } }
            </style>
        </head>
        <body>
            <div class="report-card">
                <div class="header">
                    <div class="school-name">🏫 St Jude Bukomansimbi</div>
                    <div class="school-sub">Primary School - Academic Report Card</div>
                    <div class="report-type">
                        <?php 
                        if ($exam_type !== 'all') {
                            echo strtoupper($exam_type) . " REPORT";
                        } else {
                            echo "FULL ACADEMIC REPORT";
                        }
                        ?>
                    </div>
                </div>
                <div class="student-info">
                    <div>
                        <div class="info-item"><span class="label">Student Name</span><span class="value"><?= htmlspecialchars($student['name']) ?></span></div>
                        <div class="info-item" style="margin-top:10px;"><span class="label">Class</span><span class="value"><?= htmlspecialchars($student['class']) ?></span></div>
                        <div class="info-item" style="margin-top:10px;"><span class="label">Email</span><span class="value"><?= htmlspecialchars($student['email']) ?></span></div>
                        <div class="info-item" style="margin-top:10px;"><span class="label">Phone</span><span class="value"><?= htmlspecialchars($student['phone']) ?></span></div>
                    </div>
                    <div class="student-photo">
                        <?php if(!empty($student['image']) && file_exists($student['image'])): ?>
                            <img src="<?= $student['image'] ?>" alt="Student Photo">
                        <?php else: ?>
                            <div class="placeholder"><?= strtoupper(substr($student['name'], 0, 1)) ?></div>
                        <?php endif; ?>
                        <div style="margin-top:8px; font-size:0.8rem; color:#718096;">Admission: <?= date('F j, Y', strtotime($student['admission_date'] ?? date('Y-m-d'))) ?></div>
                        <div style="font-size:0.8rem; color:#718096;">Report Date: <?= date('F j, Y') ?></div>
                    </div>
                </div>
                
                <?php if(count($results) > 0): ?>
                    <table>
                        <thead><tr><th>Term</th><th>Subject</th><th>Marks (%)</th><th>Grade</th><th>Exam Type</th></tr></thead>
                        <tbody>
                            <?php $total_marks = 0; $subject_count = 0; $term_subjects = []; foreach($results as $r): $marks = $r['marks']; $total_marks += $marks; $subject_count++; $term_subjects[$r['term']][] = $marks; $grade = ''; $grade_class = ''; if ($marks >= 80) { $grade = 'A'; $grade_class = 'grade-A'; } elseif ($marks >= 70) { $grade = 'B'; $grade_class = 'grade-B'; } elseif ($marks >= 60) { $grade = 'C'; $grade_class = 'grade-C'; } elseif ($marks >= 50) { $grade = 'D'; $grade_class = 'grade-D'; } else { $grade = 'F'; $grade_class = 'grade-F'; } $exam_class = $r['exam_type'] === 'Mid Term' ? 'midterm' : 'endterm'; ?>
                                <tr><td>Term <?= $r['term'] ?></td><td><?= htmlspecialchars($r['subject']) ?></td><td><strong><?= $marks ?>%</strong></td><td><span class="grade <?= $grade_class ?>"><?= $grade ?></span></td><td><span class="exam-badge <?= $exam_class ?>"><?= htmlspecialchars($r['exam_type']) ?></span></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php $average = $subject_count > 0 ? round($total_marks / $subject_count, 1) : 0; $overall_grade = ''; if ($average >= 80) { $overall_grade = 'A - Excellent'; } elseif ($average >= 70) { $overall_grade = 'B - Very Good'; } elseif ($average >= 60) { $overall_grade = 'C - Good'; } elseif ($average >= 50) { $overall_grade = 'D - Satisfactory'; } else { $overall_grade = 'F - Needs Improvement'; } $term_averages = []; foreach($term_subjects as $term => $marks) { $term_averages[$term] = round(array_sum($marks) / count($marks), 1); } ?>
                    
                    <div class="summary">
                        <div class="summary-item"><div class="number"><?= $subject_count ?></div><div class="label">Subjects Taken</div></div>
                        <div class="summary-item"><div class="number"><?= $total_marks ?>%</div><div class="label">Total Marks</div></div>
                        <div class="summary-item"><div class="number"><?= $average ?>%</div><div class="label">Average Score</div></div>
                        <div class="summary-item"><div class="number" style="font-size:1.2rem;"><?= $overall_grade ?></div><div class="label">Overall Performance</div></div>
                    </div>
                    
                    <?php if(count($term_averages) > 1): ?>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap:10px; margin:15px 0; padding:15px; background:#f7fafc; border-radius:10px;">
                        <?php foreach($term_averages as $term => $avg): ?>
                            <div style="text-align:center;"><div style="font-size:0.7rem; color:#718096;">Term <?= $term ?> Average</div><div style="font-size:1.5rem; font-weight:700; color:#f7971e;"><?= $avg ?>%</div></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="remarks">
                        <strong style="color:#f7971e;">📝 Teacher's Remarks</strong>
                        <div class="remark-text" style="margin-top:5px;">
                            <?php if ($average >= 80) { echo "Excellent performance! Keep up the great work. {$student['name']} shows exceptional understanding of all subjects."; } elseif ($average >= 70) { echo "Very good performance. {$student['name']} is doing well and shows good understanding of the material."; } elseif ($average >= 60) { echo "Good performance. {$student['name']} is making progress. Encourage more reading and practice."; } elseif ($average >= 50) { echo "Satisfactory performance. {$student['name']} needs to put more effort into studies."; } else { echo "Needs improvement. {$student['name']} requires extra attention and support in all subjects."; } ?>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div style="text-align:center; padding:40px; color:#718096;">
                        <i class="fas fa-info-circle" style="font-size:3rem; color:#f7971e;"></i>
                        <p style="margin-top:15px; font-size:1.1rem;">No results recorded for this student yet.</p>
                    </div>
                <?php endif; ?>
                
                <?php if($fee_data && $fee_data['total'] > 0): ?>
                <div class="fee-section">
                    <h4 style="margin-bottom:10px;">💰 Fee Summary (UGX)</h4>
                    <div class="fee-item"><span>Total Fees</span><strong>UGX <?= number_format($fee_data['total'], 0) ?></strong></div>
                    <div class="fee-item"><span>Paid</span><strong style="color:#48bb78;">UGX <?= number_format($fee_data['paid'], 0) ?></strong></div>
                    <div class="fee-item"><span>Balance</span><strong style="color:#fc8181;">UGX <?= number_format($fee_data['total'] - $fee_data['paid'], 0) ?></strong></div>
                </div>
                <?php endif; ?>
                
                <div class="signature-section">
                    <div><div class="signature-label">Class Teacher Signature</div><div class="signature-line"></div><div style="font-size:0.7rem; color:#a0aec0; margin-top:3px;">Name & Date</div></div>
                    <div><div class="signature-label">Head Teacher Signature</div><div class="signature-line"></div><div style="font-size:0.7rem; color:#a0aec0; margin-top:3px;">Name & Date</div></div>
                </div>
                
                <div style="margin-top:25px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button onclick="window.print()" class="print-btn"><i class="fas fa-print"></i> Print Report</button>
                    <a href="?page=view_student" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>
                    <?php if($term !== 'all' || $exam_type !== 'all'): ?>
                        <a href="?page=print_student_report&id=<?= $student_id ?>&term=all&exam_type=all" class="btn btn-primary" style="background:linear-gradient(135deg,#667eea,#764ba2); color:white; padding:14px 30px; border-radius:10px; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:8px;">
                            <i class="fas fa-file-alt"></i> Full Report
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>St Jude Bukomansimbi Primary School</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f4f8; min-height: 100vh; }
        
        /* Login Styles */
        .login-wrapper { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); padding: 20px; }
        .login-container { max-width: 420px; width: 100%; background: white; border-radius: 24px; padding: 40px; box-shadow: 0 30px 60px rgba(0,0,0,0.3); }
        .login-container .logo { text-align: center; margin-bottom: 30px; }
        .login-container .logo .icon-wrapper { display: inline-block; background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); padding: 20px; border-radius: 50%; color: white; font-size: 2.5rem; }
        .login-container .logo h2 { margin-top: 15px; font-weight: 800; font-size: 1.5rem; color: #1a1a2e; }
        .login-container .logo .subtitle { color: #718096; font-size: 0.9rem; }
        .form-card { background: white; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; font-size: 0.85rem; color: #4a5568; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 1rem; transition: 0.3s; font-family: 'Inter', sans-serif; }
        .form-control:focus { outline: none; border-color: #f7971e; box-shadow: 0 0 0 4px rgba(247,151,30,0.1); }
        .btn { padding: 14px 30px; border: none; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; font-family: 'Inter', sans-serif; }
        .btn-primary { background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); color: #1a1a2e; width: 100%; justify-content: center; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(247,151,30,0.4); }
        .btn-success { background: #48bb78; color: white; }
        .btn-success:hover { background: #38a169; transform: translateY(-2px); }
        .btn-danger { background: #fc8181; color: white; }
        .btn-danger:hover { background: #f56565; }
        .btn-outline { background: transparent; border: 2px solid #e2e8f0; color: #4a5568; }
        .btn-outline:hover { background: #f7fafc; }
        .btn-sm { padding: 8px 16px; font-size: 0.85rem; }
        .btn-info { background: #63b3ed; color: white; }
        .btn-info:hover { background: #4299e1; }
        .btn-whatsapp { background: #25D366; color: white; }
        .btn-whatsapp:hover { background: #1da851; transform: translateY(-2px); }
        .btn-purple { background: #9f7aea; color: white; }
        .btn-purple:hover { background: #805ad5; transform: translateY(-2px); }
        .btn-arrival { background: linear-gradient(135deg, #f7971e, #ffd200); color: #1a1a2e; }
        .btn-arrival:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(247,151,30,0.3); }
        .btn-departure { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-departure:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.3); }
        .btn-delete { background: #fc8181; color: white; }
        .btn-delete:hover { background: #f56565; transform: translateY(-2px); }
        .btn-copy { border: 2px solid #25D366; color: #25D366; background: white; padding: 12px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-copy:hover { background: #25D366; color: white; }
        
        .alert { padding: 15px 20px; border-radius: 12px; margin: 15px 0; font-weight: 500; }
        .alert-error { background: #fed7d7; color: #9b2c2c; border-left: 4px solid #fc8181; }
        .alert-success { background: #c6f6d5; color: #276749; border-left: 4px solid #48bb78; }
        .alert-info { background: #bee3f8; color: #2a4365; border-left: 4px solid #63b3ed; }
        .alert-whatsapp { background: #d4f8e8; color: #1a7a4a; border-left: 4px solid #25D366; }
        .info-box { background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%); padding: 15px; border-radius: 12px; text-align: center; color: #856404; border-left: 4px solid #ffd200; margin-bottom: 15px; }
        
        /* Sidebar */
        .dashboard-wrapper { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); padding: 25px 0; position: fixed; height: 100vh; overflow-y: auto; left: 0; top: 0; z-index: 1000; transition: all 0.3s ease; }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: transparent; }
        .sidebar::-webkit-scrollbar-thumb { background: #f7971e; border-radius: 10px; }
        .sidebar-brand { padding: 0 25px 25px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-brand .school-icon { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
        .sidebar-brand .school-icon i { font-size: 2rem; color: #ffd200; background: rgba(255,210,0,0.15); padding: 10px; border-radius: 12px; }
        .sidebar-brand h2 { color: white; font-size: 1.1rem; font-weight: 700; line-height: 1.3; }
        .sidebar-brand .school-sub { color: rgba(255,255,255,0.6); font-size: 0.7rem; font-weight: 500; letter-spacing: 1px; text-transform: uppercase; }
        .sidebar-user { padding: 15px 25px; background: rgba(255,255,255,0.05); margin: 0 15px 20px; border-radius: 12px; display: flex; align-items: center; gap: 12px; }
        .sidebar-user .avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); display: flex; align-items: center; justify-content: center; color: #1a1a2e; font-weight: 700; font-size: 1.1rem; }
        .sidebar-user .user-info .name { color: white; font-weight: 600; font-size: 0.9rem; }
        .sidebar-user .user-info .role { color: rgba(255,255,255,0.5); font-size: 0.75rem; }
        .sidebar-nav { padding: 0 15px; }
        .sidebar-nav .nav-label { color: rgba(255,255,255,0.3); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 2px; padding: 10px 12px 6px; font-weight: 600; }
        .sidebar-nav a { display: flex; align-items: center; gap: 14px; padding: 12px 18px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 10px; margin-bottom: 4px; transition: 0.3s; font-weight: 500; font-size: 0.9rem; }
        .sidebar-nav a i { width: 22px; font-size: 1.1rem; text-align: center; }
        .sidebar-nav a:hover { background: rgba(255,255,255,0.08); color: white; }
        .sidebar-nav a.active { background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); color: #1a1a2e; font-weight: 600; }
        .sidebar-nav a.active i { color: #1a1a2e; }
        .sidebar-nav .logout-link { margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 15px; color: rgba(255,255,255,0.5); }
        .sidebar-nav .logout-link:hover { color: #fc8181; background: rgba(252,129,129,0.1); }
        
        /* Main Content */
        .main-content { margin-left: 280px; flex: 1; padding: 30px; min-height: 100vh; background: #f0f4f8; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 25px; background: white; padding: 18px 25px; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        .top-bar .page-title h2 { font-size: 1.5rem; font-weight: 700; color: #1a1a2e; }
        .top-bar .page-title p { color: #718096; font-size: 0.85rem; }
        .hamburger { display: none; background: none; border: none; font-size: 1.5rem; color: #1a1a2e; cursor: pointer; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px 24px; border: 1px solid #e8ecf0; display: flex; align-items: center; gap: 15px; transition: 0.3s; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
        .stat-icon.gold { background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); }
        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); }
        .stat-icon.pink { background: linear-gradient(135deg, #fc8181 0%, #f56565 100%); }
        .stat-icon.purple { background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%); }
        .stat-content h3 { font-size: 1.8rem; font-weight: 700; color: #1a1a2e; }
        .stat-content p { color: #718096; font-size: 0.85rem; font-weight: 500; }
        
        /* Form Card */
        .form-card { background: white; padding: 25px; border-radius: 16px; border: 1px solid #e8ecf0; margin-bottom: 25px; }
        .form-card h3 { margin-bottom: 20px; color: #1a1a2e; font-weight: 700; }
        .form-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .form-row .form-group { flex: 1; min-width: 150px; margin-bottom: 0; }
        .form-row .form-group.full-width { flex: 0 0 100%; }
        
        /* Tables */
        .table-responsive { overflow-x: auto; margin: 15px 0; border-radius: 12px; border: 1px solid #e8ecf0; background: white; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th { background: #f7fafc; padding: 14px 18px; text-align: left; border-bottom: 2px solid #e8ecf0; font-weight: 600; color: #4a5568; }
        td { padding: 12px 18px; border-bottom: 1px solid #e8ecf0; vertical-align: middle; }
        tr:hover { background: #f7fafc; }
        .student-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #e8ecf0; }
        .student-avatar-placeholder { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); display: inline-flex; align-items: center; justify-content: center; color: #1a1a2e; font-weight: 700; font-size: 0.9rem; }
        .badge { display: inline-block; padding: 4px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: #c6f6d5; color: #276749; }
        .badge-danger { background: #fed7d7; color: #9b2c2c; }
        .badge-warning { background: #fefcbf; color: #975a16; }
        .badge-info { background: #bee3f8; color: #2a4365; }
        .badge-arrival { background: #fff3cd; color: #856404; }
        .badge-departure { background: #e8d5f5; color: #553c9a; }
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .action-buttons .btn { padding: 6px 12px; font-size: 0.8rem; }
        .notif-panel { background: linear-gradient(135deg, #fff9e6 0%, #fff3cd 100%); border-radius: 12px; padding: 16px 24px; margin: 20px 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; border-left: 4px solid #ffd200; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 2px solid #e8ecf0; display: flex; justify-content: space-between; flex-wrap: wrap; color: #a0aec0; font-size: 0.85rem; }
        .grade-A { color: #276749; background: #c6f6d5; padding: 2px 10px; border-radius: 12px; font-weight: 700; }
        .grade-B { color: #2a4365; background: #bee3f8; padding: 2px 10px; border-radius: 12px; font-weight: 700; }
        .grade-C { color: #975a16; background: #fefcbf; padding: 2px 10px; border-radius: 12px; font-weight: 700; }
        .grade-D { color: #9b2c2c; background: #fed7d7; padding: 2px 10px; border-radius: 12px; font-weight: 700; }
        .grade-F { color: #9b2c2c; background: #fed7d7; padding: 2px 10px; border-radius: 12px; font-weight: 700; }
        
        /* WhatsApp Alert Styles - IMPROVED */
        .alert-whatsapp { background: #d4f8e8; border-left: 4px solid #25D366; padding: 20px; border-radius: 12px; margin: 20px 0; }
        .alert-whatsapp .preview-box { background: white; padding: 15px; border-radius: 10px; border: 1px solid #e8ecf0; margin-top: 10px; white-space: pre-wrap; font-size: 0.9rem; max-height: 200px; overflow-y: auto; }
        .whatsapp-button-group { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .whatsapp-button-group .btn { flex: 1; min-width: 120px; justify-content: center; }
        
        /* WhatsApp Modal */
        .whatsapp-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; }
        .whatsapp-modal.active { display: flex; }
        .whatsapp-modal-content { background: white; border-radius: 20px; padding: 30px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalSlideIn 0.3s ease; }
        .whatsapp-modal-content .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .whatsapp-modal-content .modal-header h3 { color: #1a1a2e; }
        .whatsapp-modal-content .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #a0aec0; }
        .whatsapp-modal-content .close-btn:hover { color: #1a1a2e; }
        .whatsapp-modal-content .message-box { background: #f7fafc; padding: 15px; border-radius: 10px; white-space: pre-wrap; font-family: monospace; font-size: 0.9rem; margin: 10px 0; max-height: 300px; overflow-y: auto; }
        
        /* Delete Confirmation Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998; justify-content: center; align-items: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; border-radius: 20px; padding: 40px; max-width: 450px; width: 90%; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: modalSlideIn 0.3s ease; }
        @keyframes modalSlideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-content .modal-icon { font-size: 4rem; color: #fc8181; margin-bottom: 15px; }
        .modal-content h3 { font-size: 1.5rem; color: #1a1a2e; margin-bottom: 10px; }
        .modal-content p { color: #718096; margin-bottom: 25px; line-height: 1.6; }
        .modal-content .modal-buttons { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .modal-content .modal-buttons .btn { min-width: 120px; justify-content: center; }
        
        /* Bulk Attendance Styles */
        .attendance-table { width: 100%; border-collapse: collapse; }
        .attendance-table th { background: #f7fafc; padding: 12px; text-align: left; border-bottom: 2px solid #e8ecf0; }
        .attendance-table td { padding: 10px 12px; border-bottom: 1px solid #e8ecf0; }
        .attendance-table tr:hover { background: #f7fafc; }
        .select-all-container { display: flex; align-items: center; gap: 10px; margin: 15px 0; padding: 15px; background: #f7fafc; border-radius: 10px; }
        .select-all-container label { font-weight: 600; color: #4a5568; cursor: pointer; }
        .attendance-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #f7971e; }
        .student-status-badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .status-present { background: #c6f6d5; color: #276749; }
        .status-absent { background: #fed7d7; color: #9b2c2c; }
        .status-late { background: #fefcbf; color: #975a16; }
        
        .attendance-type-selector { display: flex; gap: 15px; padding: 12px 20px; background: #f7fafc; border-radius: 12px; border: 1px solid #e8ecf0; margin-bottom: 15px; }
        .attendance-type-selector label { display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer; padding: 8px 16px; border-radius: 8px; transition: 0.3s; }
        .attendance-type-selector label:hover { background: #edf2f7; }
        .attendance-type-selector input[type="radio"] { width: 18px; height: 18px; accent-color: #f7971e; }
        .attendance-type-selector .arrival-label { color: #856404; }
        .attendance-type-selector .departure-label { color: #553c9a; }
        
        .time-indicator { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 0.7rem; font-weight: 600; }
        .time-morning { background: #fff3cd; color: #856404; }
        .time-evening { background: #e8d5f5; color: #553c9a; }
        
        /* Responsive */
        @media (max-width: 992px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); } .main-content { margin-left: 0; } .hamburger { display: block; } .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; } .sidebar-overlay.active { display: block; } }
        @media (max-width: 600px) { .stats-grid { grid-template-columns: 1fr 1fr; } .form-row { flex-direction: column; } .form-row .form-group { width: 100%; } .main-content { padding: 15px; } .top-bar { padding: 15px; } }
        @media (max-width: 400px) { .stats-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php
// ============================================
// LOGIN PAGE
// ============================================
if ($page === 'login') {
?>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="logo">
                <div class="icon-wrapper"><i class="fas fa-graduation-cap"></i></div>
                <h2>St Jude Bukomansimbi</h2>
                <div class="subtitle">Primary School Management System</div>
            </div>
            <?php if (isset($login_error)): ?>
                <div class="alert alert-error"><?= $login_error ?></div>
            <?php endif; ?>
            <div class="info-box">
                <strong>🔑 Login:</strong> admin / admin123
            </div>
            <div class="form-card">
                <form method="post">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Username</label>
                        <input type="text" name="username" class="form-control" placeholder="admin" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" value="admin123" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php
    exit;
}

// ============================================
// DASHBOARD - Get stats
// ============================================
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
$totalFees = $pdo->query("SELECT SUM(amount) FROM fees WHERE paid = 1")->fetchColumn();
$presentToday = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'Present'")->fetchColumn();
$totalResults = $pdo->query("SELECT COUNT(*) FROM results")->fetchColumn();

$userName = $_SESSION['user_name'] ?? 'User';
$initial = strtoupper(substr($userName, 0, 1));
$activePage = $page;
?>

<div class="dashboard-wrapper">
    
    <!-- Sidebar Overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="school-icon">
                <i class="fas fa-school"></i>
                <div>
                    <h2>St Jude Bukomansimbi</h2>
                    <div class="school-sub">Primary School</div>
                </div>
            </div>
        </div>
        
        <div class="sidebar-user">
            <div class="avatar"><?= $initial ?></div>
            <div class="user-info">
                <div class="name"><?= htmlspecialchars($userName) ?></div>
                <div class="role">Administrator</div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-label">Main Menu</div>
            <a href="?page=dashboard" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
            <a href="?page=register_student" class="<?= $activePage === 'register_student' ? 'active' : '' ?>"><i class="fas fa-user-plus"></i> Register Student</a>
            <a href="?page=register_teacher" class="<?= $activePage === 'register_teacher' ? 'active' : '' ?>"><i class="fas fa-chalkboard-teacher"></i> Register Teacher</a>
            <a href="?page=attendance" class="<?= $activePage === 'attendance' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> Attendance</a>
            <a href="?page=fees" class="<?= $activePage === 'fees' ? 'active' : '' ?>"><i class="fas fa-coins"></i> Fees</a>
            <a href="?page=search_student" class="<?= $activePage === 'search_student' ? 'active' : '' ?>"><i class="fas fa-search"></i> Search Student</a>
            <a href="?page=view_student" class="<?= $activePage === 'view_student' ? 'active' : '' ?>"><i class="fas fa-users"></i> View Students</a>
            <a href="?page=print_report" class="<?= $activePage === 'print_report' ? 'active' : '' ?>"><i class="fas fa-print"></i> Print Report</a>
            <a href="?page=add_result" class="<?= $activePage === 'add_result' ? 'active' : '' ?>"><i class="fas fa-file-alt"></i> Add Result</a>
            <a href="?page=academic_calendar" class="<?= $activePage === 'academic_calendar' ? 'active' : '' ?>"><i class="fas fa-calendar-alt"></i> Academic Calendar</a>
            <a href="?logout=1" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <button class="hamburger" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <h2><?php $pageTitles = ['dashboard' => 'Dashboard', 'register_student' => 'Register Student', 'register_teacher' => 'Register Teacher', 'attendance' => 'Attendance', 'fees' => 'Fees Management', 'search_student' => 'Search Student', 'view_student' => 'View Students', 'print_report' => 'Print Report', 'add_result' => 'Add Result', 'academic_calendar' => 'Academic Calendar']; echo $pageTitles[$page] ?? 'Dashboard'; ?></h2>
                <p>St Jude Bukomansimbi Primary School</p>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="color:#718096; font-size:0.9rem;"><?= date('l, F j, Y') ?></span>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <?php if ($page === 'dashboard'): ?>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon gold"><i class="fas fa-users"></i></div><div class="stat-content"><h3><?= $totalStudents ?></h3><p>Total Students</p></div></div>
            <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-chalkboard-teacher"></i></div><div class="stat-content"><h3><?= $totalTeachers ?></h3><p>Total Teachers</p></div></div>
            <div class="stat-card"><div class="stat-icon green"><i class="fas fa-coins"></i></div><div class="stat-content"><h3>UGX <?= number_format($totalFees ?: 0, 0) ?></h3><p>Fees Collected</p></div></div>
            <div class="stat-card"><div class="stat-icon pink"><i class="fas fa-check-circle"></i></div><div class="stat-content"><h3><?= $presentToday ?></h3><p>Present Today</p></div></div>
            <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-file-alt"></i></div><div class="stat-content"><h3><?= $totalResults ?></h3><p>Results Recorded</p></div></div>
        </div>
        <?php endif; ?>
        
        <!-- Notifications -->
        <?php if (isset($_SESSION['notification'])): ?>
            <div class="alert alert-success" style="border-left: 4px solid #48bb78;">
                <i class="fas fa-check-circle"></i> <?= $_SESSION['notification'] ?>
            </div>
        <?php unset($_SESSION['notification']); endif; ?>
        
        <!-- WhatsApp Notification Panel - IMPROVED WITH COPY FUNCTION -->
        <?php if (isset($_SESSION['whatsapp_link'])): ?>
        <div class="alert alert-whatsapp">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fab fa-whatsapp" style="font-size: 2rem; color: #25D366;"></i>
                    <div>
                        <strong style="color: #1a7a4a;">WhatsApp Notification Ready!</strong>
                        <div style="font-size: 0.9rem; color: #1a7a4a; margin-top: 3px;">
                            Send to parent of <strong><?= htmlspecialchars($_SESSION['whatsapp_student']) ?></strong>
                            <span style="display: inline-block; background: #e8f5e9; padding: 2px 10px; border-radius: 10px; font-size: 0.8rem; margin-left: 5px;">
                                📱 <?= htmlspecialchars($_SESSION['whatsapp_phone'] ?? '') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Message Preview -->
            <div class="preview-box" id="whatsappMessagePreview">
                <div style="font-size: 0.8rem; color: #718096; margin-bottom: 5px;">📝 Message Preview</div>
                <div style="font-size: 0.9rem; color: #1a1a2e; white-space: pre-wrap; font-family: monospace; background: #f7fafc; padding: 10px; border-radius: 8px; max-height: 150px; overflow-y: auto;" id="messageText">
                    <?php 
                    $url = $_SESSION['whatsapp_link'];
                    $parts = parse_url($url);
                    parse_str($parts['query'] ?? '', $query);
                    $messageText = urldecode($query['text'] ?? '');
                    echo htmlspecialchars($messageText);
                    ?>
                </div>
            </div>
            
            <!-- WhatsApp Buttons -->
            <div class="whatsapp-button-group">
                <!-- Open WhatsApp App (Mobile) -->
                <a href="<?= $_SESSION['whatsapp_link'] ?>" 
                   target="_blank" 
                   class="btn btn-whatsapp" 
                   style="background: #25D366; color: white; padding: 12px 20px; border-radius: 10px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; flex:1; justify-content:center;">
                    <i class="fab fa-whatsapp"></i> Open WhatsApp
                </a>
                
                <!-- Copy Message Button - FIXED -->
                <button onclick="copyWhatsAppMessage()" 
                        class="btn-copy" 
                        style="border: 2px solid #25D366; color: #25D366; background: white; padding: 12px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; flex:1; justify-content:center; transition: all 0.3s;">
                    <i class="fas fa-copy"></i> Copy Message
                </button>
                
                <!-- Show Message Button -->
                <button onclick="showFullMessage()" 
                        class="btn btn-info" 
                        style="background: #63b3ed; color: white; padding: 12px 20px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; flex:1; justify-content:center;">
                    <i class="fas fa-eye"></i> View Full Message
                </button>
            </div>
        </div>
        
        <!-- Full Message Modal -->
        <div class="whatsapp-modal" id="whatsappModal">
            <div class="whatsapp-modal-content">
                <div class="modal-header">
                    <h3><i class="fab fa-whatsapp" style="color:#25D366;"></i> WhatsApp Message</h3>
                    <button class="close-btn" onclick="closeWhatsAppModal()">&times;</button>
                </div>
                <div style="font-size:0.8rem; color:#718096; margin-bottom:5px;">📝 Full Message</div>
                <div class="message-box" id="fullMessageBox">
                    <?php echo htmlspecialchars($messageText); ?>
                </div>
                <div style="margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button onclick="copyFullMessage()" class="btn btn-copy" style="flex:1; justify-content:center; border:2px solid #25D366; color:#25D366; background:white; padding:12px 20px; border-radius:10px; font-weight:600; cursor:pointer;">
                        <i class="fas fa-copy"></i> Copy Message
                    </button>
                    <a href="<?= $_SESSION['whatsapp_link'] ?>" target="_blank" class="btn btn-whatsapp" style="flex:1; justify-content:center; background:#25D366; color:white; padding:12px 20px; border-radius:10px; text-decoration:none; font-weight:600; display:inline-flex; align-items:center; gap:8px;">
                        <i class="fab fa-whatsapp"></i> Open WhatsApp
                    </a>
                </div>
            </div>
        </div>
        
        <script>
        // Store the message for JavaScript access
        var whatsappMessage = <?= json_encode($messageText) ?>;
        
        function copyWhatsAppMessage() {
            const message = whatsappMessage;
            if (message) {
                navigator.clipboard.writeText(message).then(function() {
                    const btn = document.querySelector('.btn-copy');
                    if (btn) {
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        btn.style.background = '#48bb78';
                        btn.style.color = 'white';
                        btn.style.borderColor = '#48bb78';
                        setTimeout(function() {
                            btn.innerHTML = originalText;
                            btn.style.background = '';
                            btn.style.color = '';
                            btn.style.borderColor = '';
                        }, 3000);
                    }
                }).catch(function(err) {
                    // Fallback - select the message text
                    const preview = document.getElementById('messageText');
                    if (preview) {
                        const range = document.createRange();
                        range.selectNode(preview);
                        window.getSelection().removeAllRanges();
                        window.getSelection().addRange(range);
                        try {
                            document.execCommand('copy');
                            alert('Message copied to clipboard!');
                        } catch(e) {
                            alert('Please copy the message manually from the preview above.');
                        }
                    }
                });
            }
        }
        
        function showFullMessage() {
            document.getElementById('whatsappModal').classList.add('active');
        }
        
        function closeWhatsAppModal() {
            document.getElementById('whatsappModal').classList.remove('active');
        }
        
        function copyFullMessage() {
            const message = whatsappMessage;
            if (message) {
                navigator.clipboard.writeText(message).then(function() {
                    const btn = document.querySelector('.whatsapp-modal-content .btn-copy');
                    if (btn) {
                        const originalText = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                        btn.style.background = '#48bb78';
                        btn.style.color = 'white';
                        btn.style.borderColor = '#48bb78';
                        setTimeout(function() {
                            btn.innerHTML = originalText;
                            btn.style.background = '';
                            btn.style.color = '';
                            btn.style.borderColor = '';
                        }, 3000);
                    }
                }).catch(function(err) {
                    // Select and copy manually
                    const box = document.getElementById('fullMessageBox');
                    if (box) {
                        const range = document.createRange();
                        range.selectNode(box);
                        window.getSelection().removeAllRanges();
                        window.getSelection().addRange(range);
                        document.execCommand('copy');
                        alert('Message copied to clipboard!');
                    }
                });
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('whatsappModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeWhatsAppModal();
            }
        });
        </script>
        <?php unset($_SESSION['whatsapp_link']); unset($_SESSION['whatsapp_student']); unset($_SESSION['whatsapp_phone']); unset($_SESSION['whatsapp_message']); endif; ?>
        
        <!-- Bulk WhatsApp Links -->
        <?php if (isset($_SESSION['bulk_whatsapp_links'])): ?>
        <div class="alert alert-whatsapp">
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 10px;">
                <div>
                    <i class="fab fa-whatsapp" style="font-size: 2rem; color: #25D366;"></i>
                    <strong style="color: #1a7a4a;">Bulk WhatsApp Notifications Ready!</strong>
                    <div style="font-size: 0.9rem; color: #1a7a4a; margin-top: 3px;">
                        Send to <?= count($_SESSION['bulk_whatsapp_links']) ?> parents
                    </div>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px;">
                <?php foreach ($_SESSION['bulk_whatsapp_links'] as $link): ?>
                    <a href="<?= $link['url'] ?>" target="_blank" class="btn btn-whatsapp btn-sm" style="text-align: center; justify-content: center; padding: 10px 15px; background: #25D366; color: white; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($link['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php unset($_SESSION['bulk_whatsapp_links']); unset($_SESSION['bulk_count']); endif; ?>
        
        <!-- ============================================
             PAGE CONTENT
             ============================================ -->
        <?php
        function renderPage($page, $pdo) {
            switch ($page) {
                case 'dashboard': renderDashboard($pdo); break;
                case 'register_student': renderRegisterStudent($pdo); break;
                case 'register_teacher': renderRegisterTeacher($pdo); break;
                case 'attendance': renderAttendance($pdo); break;
                case 'fees': renderFees($pdo); break;
                case 'search_student': renderSearchStudent($pdo); break;
                case 'view_student': renderViewStudents($pdo); break;
                case 'print_report': renderPrintReport($pdo); break;
                case 'add_result': renderAddResult($pdo); break;
                case 'academic_calendar': renderAcademicCalendar($pdo); break;
                default: echo "<p style='text-align:center; color:#718096; padding:40px 0;'>Select an option from the menu.</p>";
            }
        }

        function renderDashboard($pdo) {
            $recentStudents = $pdo->query("SELECT * FROM students ORDER BY id DESC LIMIT 5")->fetchAll();
            ?>
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px;">
                <div style="background:white; border-radius:16px; padding:20px; border:1px solid #e8ecf0;">
                    <h4 style="margin-bottom:15px; color:#1a1a2e;">Recent Students</h4>
                    <?php if(count($recentStudents) > 0): ?>
                        <?php foreach($recentStudents as $s): ?>
                            <div style="display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid #f0f4f8;">
                                <?php if(!empty($s['image']) && file_exists($s['image'])): ?>
                                    <img src="<?= $s['image'] ?>" style="width:35px;height:35px;border-radius:50%;object-fit:cover;">
                                <?php else: ?>
                                    <div style="width:35px;height:35px;border-radius:50%;background:linear-gradient(135deg,#f7971e,#ffd200);display:flex;align-items:center;justify-content:center;color:#1a1a2e;font-weight:700;"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:600;font-size:0.9rem;"><?= htmlspecialchars($s['name']) ?></div>
                                    <div style="color:#a0aec0;font-size:0.75rem;"><?= htmlspecialchars($s['class']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#a0aec0;text-align:center;padding:20px 0;">No students registered yet.</p>
                    <?php endif; ?>
                </div>
                <div style="background:white; border-radius:16px; padding:20px; border:1px solid #e8ecf0;">
                    <h4 style="margin-bottom:15px; color:#1a1a2e;">Quick Stats</h4>
                    <div style="display:grid; gap:10px;">
                        <div style="background:#f7fafc;padding:12px 16px;border-radius:8px;display:flex;justify-content:space-between;"><span style="color:#4a5568;">👨‍🎓 Students</span><span style="font-weight:700;color:#f7971e;"><?= $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn() ?></span></div>
                        <div style="background:#f7fafc;padding:12px 16px;border-radius:8px;display:flex;justify-content:space-between;"><span style="color:#4a5568;">👩‍🏫 Teachers</span><span style="font-weight:700;color:#667eea;"><?= $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn() ?></span></div>
                        <div style="background:#f7fafc;padding:12px 16px;border-radius:8px;display:flex;justify-content:space-between;"><span style="color:#4a5568;">💰 Fees Collected</span><span style="font-weight:700;color:#48bb78;">UGX <?= number_format($pdo->query("SELECT SUM(amount) FROM fees WHERE paid = 1")->fetchColumn() ?: 0, 0) ?></span></div>
                        <div style="background:#f7fafc;padding:12px 16px;border-radius:8px;display:flex;justify-content:space-between;"><span style="color:#4a5568;">✅ Present Today</span><span style="font-weight:700;color:#48bb78;"><?= $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'Present'")->fetchColumn() ?></span></div>
                    </div>
                </div>
            </div>
            <?php
        }

        function renderAcademicCalendar($pdo) {
            ?>
            <div style="background:white; border-radius:16px; padding:25px; border:1px solid #e8ecf0;">
                <h3 style="margin-bottom:20px;"><i class="fas fa-calendar-alt" style="color:#f7971e;"></i> Academic Calendar 2026</h3>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px,1fr)); gap:20px;">
                    <div style="background:#f7fafc; border-radius:12px; padding:20px; border-left:4px solid #f7971e;">
                        <h4 style="color:#f7971e;">Term 1</h4>
                        <p><strong>Start:</strong> January 13, 2026</p>
                        <p><strong>End:</strong> April 3, 2026</p>
                        <p><strong>Half Term:</strong> February 16-20, 2026</p>
                        <p><strong>Mid Term Exams:</strong> February 9-13, 2026</p>
                        <p><strong>End Term Exams:</strong> March 23-27, 2026</p>
                    </div>
                    <div style="background:#f7fafc; border-radius:12px; padding:20px; border-left:4px solid #48bb78;">
                        <h4 style="color:#48bb78;">Term 2</h4>
                        <p><strong>Start:</strong> April 20, 2026</p>
                        <p><strong>End:</strong> July 10, 2026</p>
                        <p><strong>Half Term:</strong> May 25-29, 2026</p>
                        <p><strong>Mid Term Exams:</strong> May 18-22, 2026</p>
                        <p><strong>End Term Exams:</strong> June 29 - July 3, 2026</p>
                    </div>
                    <div style="background:#f7fafc; border-radius:12px; padding:20px; border-left:4px solid #9f7aea;">
                        <h4 style="color:#9f7aea;">Term 3</h4>
                        <p><strong>Start:</strong> July 27, 2026</p>
                        <p><strong>End:</strong> October 16, 2026</p>
                        <p><strong>Half Term:</strong> August 31 - September 4, 2026</p>
                        <p><strong>Mid Term Exams:</strong> August 24-28, 2026</p>
                        <p><strong>End Term Exams:</strong> October 5-9, 2026</p>
                    </div>
                </div>
                
                <h4 style="margin:25px 0 15px;">Important Dates</h4>
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap:15px;">
                    <div style="background:#fff9e6; border-radius:10px; padding:15px; text-align:center;">
                        <div style="font-size:0.8rem; color:#856404;">January 26, 2026</div>
                        <div style="font-weight:600;">🇺🇬 NRM Liberation Day</div>
                    </div>
                    <div style="background:#fff9e6; border-radius:10px; padding:15px; text-align:center;">
                        <div style="font-size:0.8rem; color:#856404;">February 16, 2026</div>
                        <div style="font-weight:600;">🇺🇬 Archbishop Janani Luwum Day</div>
                    </div>
                    <div style="background:#fff9e6; border-radius:10px; padding:15px; text-align:center;">
                        <div style="font-size:0.8rem; color:#856404;">March 8, 2026</div>
                        <div style="font-weight:600;">👩 International Women's Day</div>
                    </div>
                    <div style="background:#fff9e6; border-radius:10px; padding:15px; text-align:center;">
                        <div style="font-size:0.8rem; color:#856404;">April 18, 2026</div>
                        <div style="font-weight:600;">✝️ Good Friday</div>
                    </div>
                    <div style="background:#fff9e6; border-radius:10px; padding:15px; text-align:center;">
                        <div style="font-size:0.8rem; color:#856404;">April 20, 2026</div>
                        <div style="font-weight:600;">🐣 Easter Monday</div>
                    </div>
                    <div style="background:#fff9e6; border-radius:10px; padding:15px; text-align:center;">
                        <div style="font-size:0.8rem; color:#856404;">June 3, 2026</div>
                        <div style="font-weight:600;">🇺🇬 Martyrs Day</div>
                    </div>
                </div>
                
                <div style="margin-top:20px; padding-top:20px; border-top:1px solid #e8ecf0; text-align:center; color:#a0aec0; font-size:0.9rem;">
                    <i class="fas fa-info-circle"></i> Academic calendar is subject to change based on national holidays and school events.
                </div>
            </div>
            <?php
        }

        function renderRegisterStudent($pdo) {
            ?>
            <div class="form-card">
                <h3><i class="fas fa-user-plus" style="color:#f7971e;"></i> Register Student</h3>
                <p style="color:#718096; margin-bottom:15px;">Add a new student to the system with their photo and contact details.</p>
                <?php if (isset($GLOBALS['success'])) echo "<div class='alert alert-success'>{$GLOBALS['success']}</div>"; ?>
                <form method="post" enctype="multipart/form-data" class="form-row">
                    <div class="form-group"><input type="text" name="name" class="form-control" placeholder="Full Name" required></div>
                    <div class="form-group"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                    <div class="form-group">
                        <select name="class" class="form-control" required>
                            <option value="">Select Class</option>
                            <option value="Primary 1">Primary 1</option>
                            <option value="Primary 2">Primary 2</option>
                            <option value="Primary 3">Primary 3</option>
                            <option value="Primary 4">Primary 4</option>
                            <option value="Primary 5">Primary 5</option>
                            <option value="Primary 6">Primary 6</option>
                            <option value="Primary 7">Primary 7</option>
                        </select>
                    </div>
                    <div class="form-group"><input type="text" name="phone" class="form-control" placeholder="Phone (for WhatsApp)" required></div>
                    <div class="form-group"><input type="file" name="image" class="form-control" accept="image/*"></div>
                    <div class="form-group"><button type="submit" name="register" class="btn btn-success"><i class="fas fa-save"></i> Register</button></div>
                </form>
            </div>
            <?php
        }

        function renderRegisterTeacher($pdo) {
            ?>
            <div class="form-card">
                <h3><i class="fas fa-chalkboard-teacher" style="color:#f7971e;"></i> Register Teacher</h3>
                <p style="color:#718096; margin-bottom:15px;">Register a new teacher to the school.</p>
                <?php if (isset($GLOBALS['success'])) echo "<div class='alert alert-success'>{$GLOBALS['success']}</div>"; ?>
                <form method="post" class="form-row">
                    <div class="form-group"><input type="text" name="name" class="form-control" placeholder="Full Name" required></div>
                    <div class="form-group"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                    <div class="form-group">
                        <select name="subject" class="form-control" required>
                            <option value="">Select Subject</option>
                            <option value="Mathematics">Mathematics</option>
                            <option value="English">English</option>
                            <option value="Science">Science</option>
                            <option value="Social Studies">Social Studies</option>
                            <option value="Religious Education">Religious Education</option>
                            <option value="Physical Education">Physical Education</option>
                            <option value="Art & Craft">Art & Craft</option>
                            <option value="Music">Music</option>
                        </select>
                    </div>
                    <div class="form-group"><input type="text" name="phone" class="form-control" placeholder="Phone"></div>
                    <div class="form-group">
                        <select name="qualification" class="form-control" required>
                            <option value="">Select Qualification</option>
                            <option value="Bachelor's Degree">Bachelor's Degree</option>
                            <option value="Diploma">Diploma</option>
                            <option value="Certificate">Certificate</option>
                            <option value="Master's Degree">Master's Degree</option>
                        </select>
                    </div>
                    <div class="form-group"><button type="submit" name="register" class="btn btn-success"><i class="fas fa-save"></i> Register</button></div>
                </form>
            </div>
            <?php
        }

        // ============================================
        // ATTENDANCE PAGE
        // ============================================
        function renderAttendance($pdo) {
            $students = $pdo->query("SELECT id, name, class, phone FROM students ORDER BY class, name")->fetchAll();
            
            $groupedStudents = [];
            foreach ($students as $s) {
                $class = $s['class'] ?? 'Unassigned';
                $groupedStudents[$class][] = $s;
            }
            
            $today = date('Y-m-d');
            $attendance_today = [];
            $attendance_stmt = $pdo->prepare("SELECT student_id, status, attendance_type FROM attendance WHERE date = ?");
            $attendance_stmt->execute([$today]);
            while ($row = $attendance_stmt->fetch(PDO::FETCH_ASSOC)) {
                $attendance_today[$row['student_id']] = [
                    'status' => $row['status'],
                    'type' => $row['attendance_type'] ?? 'arrival'
                ];
            }
            ?>
            <div class="form-card">
                <h3><i class="fas fa-clipboard-list" style="color:#f7971e;"></i> Bulk Attendance - Arrival & Departure</h3>
                <p style="color:#718096; margin-bottom:15px;">
                    <span class="time-indicator time-morning">🌅 Arrival - Morning (6:00 AM - 12:00 PM)</span>
                    <span class="time-indicator time-evening" style="margin-left:10px;">🌙 Departure - Evening (12:00 PM - 8:00 PM)</span>
                </p>
                
                <?php if (isset($GLOBALS['attendance_error'])) echo "<div class='alert alert-error'>{$GLOBALS['attendance_error']}</div>"; ?>
                <?php if (isset($GLOBALS['attendance_success'])) echo "<div class='alert alert-success'>{$GLOBALS['attendance_success']}</div>"; ?>
                
                <form method="post" id="bulkAttendanceForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="date" class="form-control" required value="<?= $today ?>">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="bulk_status" class="form-control" required>
                                <option value="Present">✅ Present</option>
                                <option value="Absent">❌ Absent</option>
                                <option value="Late">⏰ Late</option>
                            </select>
                        </div>
                        <div class="form-group" style="min-width:200px;">
                            <label>Attendance Type</label>
                            <div class="attendance-type-selector">
                                <label class="arrival-label">
                                    <input type="radio" name="attendance_type" value="arrival" checked>
                                    <i class="fas fa-sun"></i> Arrival
                                    <span style="font-size:0.7rem; color:#856404;">(Morning)</span>
                                </label>
                                <label class="departure-label">
                                    <input type="radio" name="attendance_type" value="departure">
                                    <i class="fas fa-moon"></i> Departure
                                    <span style="font-size:0.7rem; color:#553c9a;">(Evening)</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-group" style="display:flex; align-items:center; gap:10px; padding-top:20px;">
                            <input type="checkbox" name="send_whatsapp_bulk" value="1" id="send_whatsapp_bulk" checked>
                            <label for="send_whatsapp_bulk" style="margin:0; font-weight:500; color:#25D366;">
                                <i class="fab fa-whatsapp"></i> Send WhatsApp
                            </label>
                        </div>
                        <div class="form-group">
                            <button type="submit" name="bulk_attendance" class="btn btn-success" style="width:100%;">
                                <i class="fas fa-check-double"></i> Mark Selected
                            </button>
                        </div>
                    </div>
                    
                    <div class="select-all-container">
                        <input type="checkbox" id="selectAll" class="attendance-checkbox" onclick="toggleAllStudents()">
                        <label for="selectAll"><strong>Select All Students</strong></label>
                        <span style="color:#718096; font-size:0.85rem; margin-left:10px;">
                            (<?= count($students) ?> students total)
                        </span>
                        <span id="selectedCount" style="color:#f7971e; font-weight:600; margin-left:15px;">0 selected</span>
                    </div>
                    
                    <?php foreach ($groupedStudents as $class => $classStudents): ?>
                        <div style="margin-bottom:20px;">
                            <h4 style="color:#1a1a2e; background:#f7fafc; padding:10px 15px; border-radius:8px; margin-bottom:10px;">
                                <i class="fas fa-users" style="color:#f7971e;"></i> <?= htmlspecialchars($class) ?>
                                <span style="font-size:0.8rem; color:#718096; font-weight:400;">(<?= count($classStudents) ?> students)</span>
                            </h4>
                            <div class="table-responsive">
                                <table class="attendance-table">
                                    <thead>
                                        <tr>
                                            <th style="width:40px;">
                                                <input type="checkbox" class="attendance-checkbox class-select" onclick="toggleClassStudents(this)" data-class="<?= $class ?>">
                                            </th>
                                            <th style="width:50px;">#</th>
                                            <th>Student Name</th>
                                            <th>Phone</th>
                                            <th>Arrival Status</th>
                                            <th>Departure Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $counter = 1; foreach ($classStudents as $s): 
                                            $arrival_status = $attendance_today[$s['id']]['status'] ?? 'Not Marked';
                                            $arrival_type = $attendance_today[$s['id']]['type'] ?? '';
                                            $departure_status = 'Not Marked';
                                            
                                            $departure_stmt = $pdo->prepare("SELECT status FROM attendance WHERE student_id = ? AND date = ? AND attendance_type = 'departure'");
                                            $departure_stmt->execute([$s['id'], $today]);
                                            $departure_row = $departure_stmt->fetch(PDO::FETCH_ASSOC);
                                            if ($departure_row) {
                                                $departure_status = $departure_row['status'];
                                            }
                                            
                                            $arrival_class = '';
                                            if ($arrival_status === 'Present') $arrival_class = 'status-present';
                                            elseif ($arrival_status === 'Absent') $arrival_class = 'status-absent';
                                            elseif ($arrival_status === 'Late') $arrival_class = 'status-late';
                                            
                                            $departure_class = '';
                                            if ($departure_status === 'Present') $departure_class = 'status-present';
                                            elseif ($departure_status === 'Absent') $departure_class = 'status-absent';
                                            elseif ($departure_status === 'Late') $departure_class = 'status-late';
                                        ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="attendance-checkbox student-check" data-class="<?= $class ?>" onchange="updateSelectedCount()">
                                                </td>
                                                <td><?= $counter++ ?></td>
                                                <td>
                                                    <?php if(!empty($s['image']) && file_exists($s['image'])): ?>
                                                        <img src="<?= $s['image'] ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;margin-right:8px;vertical-align:middle;">
                                                    <?php else: ?>
                                                        <span style="display:inline-block;width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#f7971e,#ffd200);text-align:center;line-height:30px;color:#1a1a2e;font-weight:700;font-size:0.8rem;margin-right:8px;"><?= strtoupper(substr($s['name'],0,1)) ?></span>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($s['name']) ?>
                                                </td>
                                                <td><?= htmlspecialchars($s['phone']) ?></td>
                                                <td>
                                                    <span class="student-status-badge <?= $arrival_class ?>">
                                                        <?= $arrival_status ?>
                                                    </span>
                                                    <?php if($arrival_type === 'arrival'): ?>
                                                        <span class="time-indicator time-morning" style="margin-left:5px;">🌅</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="student-status-badge <?= $departure_class ?>">
                                                        <?= $departure_status ?>
                                                    </span>
                                                    <?php if($departure_status !== 'Not Marked'): ?>
                                                        <span class="time-indicator time-evening" style="margin-left:5px;">🌙</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>
            </div>
            
            <!-- Recent Attendance Records -->
            <?php
            $recent_attendance = $pdo->query("
                SELECT a.*, s.name, s.class 
                FROM attendance a 
                JOIN students s ON a.student_id = s.id 
                ORDER BY a.date DESC, a.attendance_type DESC, s.class, s.name 
                LIMIT 30
            ")->fetchAll();
            ?>
            <?php if(count($recent_attendance) > 0): ?>
            <div style="margin-top:20px;">
                <h4 style="margin-bottom:15px; color:#1a1a2e;">
                    <i class="fas fa-history" style="color:#f7971e;"></i> Recent Attendance Records
                </h4>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_attendance as $a): ?>
                                <tr>
                                    <td><?= $a['date'] ?></td>
                                    <td><?= htmlspecialchars($a['name']) ?></td>
                                    <td><?= htmlspecialchars($a['class']) ?></td>
                                    <td>
                                        <span class="badge <?= ($a['attendance_type'] ?? 'arrival') === 'arrival' ? 'badge-arrival' : 'badge-departure' ?>">
                                            <?= ($a['attendance_type'] ?? 'arrival') === 'arrival' ? '🌅 Arrival' : '🌙 Departure' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $a['status']==='Present'?'badge-success':($a['status']==='Late'?'badge-warning':'badge-danger') ?>">
                                            <?= $a['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <script>
            function toggleAllStudents() {
                const selectAll = document.getElementById('selectAll');
                const allCheckboxes = document.querySelectorAll('.student-check');
                allCheckboxes.forEach(cb => cb.checked = selectAll.checked);
                document.querySelectorAll('.class-select').forEach(cb => cb.checked = selectAll.checked);
                updateSelectedCount();
            }
            
            function toggleClassStudents(checkbox) {
                const className = checkbox.dataset.class;
                const classCheckboxes = document.querySelectorAll(`.student-check[data-class="${className}"]`);
                classCheckboxes.forEach(cb => cb.checked = checkbox.checked);
                updateSelectedCount();
                updateSelectAll();
            }
            
            function updateSelectedCount() {
                const checked = document.querySelectorAll('.student-check:checked').length;
                document.getElementById('selectedCount').textContent = checked + ' selected';
            }
            
            function updateSelectAll() {
                const allCheckboxes = document.querySelectorAll('.student-check');
                const checked = document.querySelectorAll('.student-check:checked');
                const selectAll = document.getElementById('selectAll');
                selectAll.checked = allCheckboxes.length > 0 && allCheckboxes.length === checked.length;
                
                document.querySelectorAll('.class-select').forEach(cb => {
                    const className = cb.dataset.class;
                    const classCheckboxes = document.querySelectorAll(`.student-check[data-class="${className}"]`);
                    cb.checked = classCheckboxes.length > 0 && 
                        classCheckboxes.length === document.querySelectorAll(`.student-check[data-class="${className}"]:checked`).length;
                });
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                updateSelectedCount();
            });
            </script>
            <?php
        }

        function renderFees($pdo) {
            $students = $pdo->query("SELECT id, name FROM students ORDER BY name")->fetchAll();
            $fees = [];
            try {
                $fees = $pdo->query("SELECT f.*, s.name FROM fees f JOIN students s ON f.student_id = s.id ORDER BY f.year DESC, f.month DESC LIMIT 20")->fetchAll();
            } catch (PDOException $e) {}
            ?>
            <div class="form-card">
                <h3><i class="fas fa-coins" style="color:#f7971e;"></i> Add Fee Record (UGX)</h3>
                <p style="color:#718096; margin-bottom:15px;">Record fee payments in Ugandan Shillings (UGX).</p>
                <?php if (isset($GLOBALS['fee_success'])) echo "<div class='alert alert-success'>{$GLOBALS['fee_success']}</div>"; ?>
                <?php if (isset($GLOBALS['fee_error'])) echo "<div class='alert alert-error'>{$GLOBALS['fee_error']}</div>"; ?>
                <form method="post" class="form-row">
                    <div class="form-group"><select name="student_id" class="form-control" required><option value="">Student</option><?php foreach($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><input type="number" name="amount" class="form-control" placeholder="Amount (UGX)" required min="0" step="1000"></div>
                    <div class="form-group"><input type="number" name="month" class="form-control" placeholder="Month (1-12)" required min="1" max="12"></div>
                    <div class="form-group"><input type="number" name="year" class="form-control" placeholder="Year" required min="2000" max="2100" value="<?= date('Y') ?>"></div>
                    <div class="form-group" style="display:flex; align-items:center; gap:10px;"><input type="checkbox" name="paid" value="1" id="paid"><label for="paid">Paid</label></div>
                    <div class="form-group"><button type="submit" name="add_fee" class="btn btn-success"><i class="fas fa-plus"></i> Add Fee</button></div>
                </form>
            </div>
            <?php if(count($fees) > 0): ?>
            <div class="table-responsive">
                <table><thead><tr><th>Student</th><th>Amount (UGX)</th><th>Month/Year</th><th>Status</th></tr></thead>
                <tbody><?php foreach($fees as $f): ?><tr><td><?= htmlspecialchars($f['name']) ?></td><td>UGX <?= number_format($f['amount'], 0) ?></td><td><?= $f['month'].'/'.$f['year'] ?></td><td><span class="badge <?= $f['paid']?'badge-success':'badge-danger' ?>"><?= $f['paid']?'Paid':'Unpaid' ?></span></td></tr><?php endforeach; ?></tbody></table>
            </div>
            <?php endif; ?>
            <?php
        }

        function renderSearchStudent($pdo) {
            $results = [];
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
                $q = '%' . $_POST['query'] . '%';
                $stmt = $pdo->prepare("SELECT * FROM students WHERE name LIKE ? OR email LIKE ? OR class LIKE ?");
                $stmt->execute([$q, $q, $q]);
                $results = $stmt->fetchAll();
            }
            ?>
            <div class="form-card">
                <h3><i class="fas fa-search" style="color:#f7971e;"></i> Search Students</h3>
                <form method="post" class="form-row">
                    <div class="form-group" style="flex:3;"><input type="text" name="query" class="form-control" placeholder="Search by name, email, or class" required></div>
                    <div class="form-group"><button type="submit" name="search" class="btn btn-primary"><i class="fas fa-search"></i> Search</button></div>
                </form>
            </div>
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <div class="table-responsive">
                    <table><thead><tr><th>Photo</th><th>ID</th><th>Name</th><th>Email</th><th>Class</th><th>Phone</th></tr></thead>
                    <tbody>
                        <?php if(count($results)): foreach($results as $r): ?>
                            <tr>
                                <td><?php if(!empty($r['image']) && file_exists($r['image'])): ?><img src="<?= $r['image'] ?>" class="student-avatar"><?php else: ?><div class="student-avatar-placeholder"><?= strtoupper(substr($r['name'], 0, 1)) ?></div><?php endif; ?></td>
                                <td><?= $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['name']) ?></td>
                                <td><?= htmlspecialchars($r['email']) ?></td>
                                <td><?= htmlspecialchars($r['class']) ?></td>
                                <td><?= htmlspecialchars($r['phone']) ?></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" style="text-align:center; color:#a0aec0; padding:30px;">No students found</td></tr>
                        <?php endif; ?>
                    </tbody></table>
                </div>
            <?php endif;
        }

        // ============================================
        // VIEW STUDENTS PAGE WITH DELETE BUTTON
        // ============================================
        function renderViewStudents($pdo) {
            $students = $pdo->query("SELECT * FROM students ORDER BY name")->fetchAll();
            ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                <h3 style="color:#1a1a2e;"><i class="fas fa-users" style="color:#f7971e;"></i> All Students</h3>
                <span style="color:#718096; font-size:0.9rem;">Total: <?= count($students) ?> students</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $s): ?>
                            <tr>
                                <td>
                                    <?php if(!empty($s['image']) && file_exists($s['image'])): ?>
                                        <img src="<?= $s['image'] ?>" class="student-avatar" alt="Student">
                                    <?php else: ?>
                                        <div class="student-avatar-placeholder"><?= strtoupper(substr($s['name'], 0, 1)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= $s['id'] ?></td>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td><?= htmlspecialchars($s['class']) ?></td>
                                <td><?= htmlspecialchars($s['phone']) ?></td>
                                <td>
                                    <div class="action-buttons" style="display:flex; flex-wrap:wrap; gap:5px;">
                                        <a href="?page=print_student_report&id=<?= $s['id'] ?>&term=all&exam_type=all" class="btn btn-primary btn-sm" target="_blank">
                                            <i class="fas fa-print"></i> Full Report
                                        </a>
                                        <a href="?page=print_student_report&id=<?= $s['id'] ?>&term=1&exam_type=Mid%20Term" class="btn btn-info btn-sm" target="_blank">
                                            <i class="fas fa-file-alt"></i> Mid Term
                                        </a>
                                        <a href="?page=print_student_report&id=<?= $s['id'] ?>&term=1&exam_type=End%20Term" class="btn btn-success btn-sm" target="_blank">
                                            <i class="fas fa-file-alt"></i> End Term
                                        </a>
                                        <?php if(!empty($s['phone'])): ?>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $s['phone']) ?>?text=Hello%20<?= urlencode($s['name']) ?>,%20this%20is%20St%20Jude%20Bukomansimbi%20Primary%20School." target="_blank" class="btn btn-whatsapp btn-sm">
                                                <i class="fab fa-whatsapp"></i> WhatsApp
                                            </a>
                                        <?php endif; ?>
                                        <button onclick="openDeleteModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['name']) ?>')" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Delete Confirmation Modal -->
            <div class="modal" id="deleteModal">
                <div class="modal-content">
                    <div class="modal-icon">🗑️</div>
                    <h3>Confirm Delete</h3>
                    <p>
                        Are you sure you want to delete <strong id="deleteStudentName"></strong>?
                        <br><br>
                        <span style="color:#fc8181; font-size:0.9rem;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            This action cannot be undone. All related records (attendance, fees, results) will also be deleted.
                        </span>
                    </p>
                    <div class="modal-buttons">
                        <button onclick="closeDeleteModal()" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <a href="#" id="deleteConfirmLink" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Yes, Delete
                        </a>
                    </div>
                </div>
            </div>
            
            <script>
            function openDeleteModal(id, name) {
                document.getElementById('deleteStudentName').textContent = name;
                document.getElementById('deleteConfirmLink').href = '?page=view_student&delete=' + id;
                document.getElementById('deleteModal').classList.add('active');
            }
            
            function closeDeleteModal() {
                document.getElementById('deleteModal').classList.remove('active');
            }
            
            document.getElementById('deleteModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteModal();
                }
            });
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeDeleteModal();
                }
            });
            </script>
            <?php
        }

        function renderPrintReport($pdo) {
            $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
            $totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
            $presentToday = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'Present'")->fetchColumn();
            $totalFees = $pdo->query("SELECT SUM(amount) FROM fees WHERE paid = 1")->fetchColumn();
            $absentToday = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'Absent'")->fetchColumn();
            $lateToday = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'Late'")->fetchColumn();
            $students = $pdo->query("SELECT * FROM students LIMIT 15")->fetchAll();
            ?>
            <div class="report-section" style="background:white; border-radius:16px; padding:25px; border:1px solid #e8ecf0;">
                <h3 style="margin-bottom:5px;"><i class="fas fa-print" style="color:#f7971e;"></i> School Report</h3>
                <p style="color:#718096; margin-bottom:20px;">St Jude Bukomansimbi Primary School - <?= date('F j, Y, g:i A') ?></p>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:15px; margin:20px 0;">
                    <div style="background:#f7fafc;padding:18px;border-radius:12px;text-align:center;border:1px solid #e8ecf0;"><div style="font-size:2rem;font-weight:800;color:#1a1a2e;"><?= $totalStudents ?></div><div style="color:#718096;font-size:0.8rem;font-weight:500;">Total Students</div></div>
                    <div style="background:#f7fafc;padding:18px;border-radius:12px;text-align:center;border:1px solid #e8ecf0;"><div style="font-size:2rem;font-weight:800;color:#1a1a2e;"><?= $totalTeachers ?></div><div style="color:#718096;font-size:0.8rem;font-weight:500;">Total Teachers</div></div>
                    <div style="background:#f7fafc;padding:18px;border-radius:12px;text-align:center;border:1px solid #e8ecf0;"><div style="font-size:2rem;font-weight:800;color:#48bb78;"><?= $presentToday ?></div><div style="color:#718096;font-size:0.8rem;font-weight:500;">Present Today</div></div>
                    <div style="background:#f7fafc;padding:18px;border-radius:12px;text-align:center;border:1px solid #e8ecf0;"><div style="font-size:2rem;font-weight:800;color:#fc8181;"><?= $absentToday ?></div><div style="color:#718096;font-size:0.8rem;font-weight:500;">Absent Today</div></div>
                    <div style="background:#f7fafc;padding:18px;border-radius:12px;text-align:center;border:1px solid #e8ecf0;"><div style="font-size:2rem;font-weight:800;color:#ed8936;"><?= $lateToday ?></div><div style="color:#718096;font-size:0.8rem;font-weight:500;">Late Today</div></div>
                    <div style="background:#f7fafc;padding:18px;border-radius:12px;text-align:center;border:1px solid #e8ecf0;"><div style="font-size:2rem;font-weight:800;color:#f7971e;">UGX <?= number_format($totalFees ?: 0, 0) ?></div><div style="color:#718096;font-size:0.8rem;font-weight:500;">Fees Collected</div></div>
                </div>
                <h4 style="margin:20px 0 10px;">Students</h4>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(140px,1fr)); gap:15px; margin-bottom:20px;">
                    <?php foreach($students as $s): ?>
                        <div style="background:#f7fafc;border-radius:12px;padding:15px;text-align:center;border:1px solid #e8ecf0;">
                            <?php if(!empty($s['image']) && file_exists($s['image'])): ?>
                                <img src="<?= $s['image'] ?>" style="width:60px;height:60px;border-radius:50%;object-fit:cover;margin-bottom:8px;border:3px solid #ffd200;">
                            <?php else: ?>
                                <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#f7971e,#ffd200);display:inline-flex;align-items:center;justify-content:center;color:#1a1a2e;font-weight:700;font-size:1.5rem;margin-bottom:8px;"><?= strtoupper(substr($s['name'],0,1)) ?></div>
                            <?php endif; ?>
                            <div style="font-weight:600;font-size:0.85rem;"><?= htmlspecialchars($s['name']) ?></div>
                            <div style="color:#a0aec0;font-size:0.75rem;"><?= htmlspecialchars($s['class']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print Report</button>
            </div>
            <?php
        }

        function renderAddResult($pdo) {
            $students = $pdo->query("SELECT id, name FROM students ORDER BY name")->fetchAll();
            $results = $pdo->query("SELECT r.*, s.name FROM results r JOIN students s ON r.student_id = s.id ORDER BY r.id DESC LIMIT 30")->fetchAll();
            ?>
            <div class="form-card">
                <h3><i class="fas fa-file-alt" style="color:#f7971e;"></i> Add Student Results</h3>
                <p style="color:#718096; margin-bottom:15px;">Enter marks for students by subject and term.</p>
                <?php if (isset($GLOBALS['result_success'])) echo "<div class='alert alert-success'>{$GLOBALS['result_success']}</div>"; ?>
                
                <h4 style="margin-bottom:10px; color:#4a5568;">Single Entry</h4>
                <form method="post" class="form-row" style="margin-bottom:20px; padding-bottom:20px; border-bottom:1px solid #e8ecf0;">
                    <div class="form-group"><select name="student_id" class="form-control" required><option value="">Student</option><?php foreach($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><input type="text" name="subject" class="form-control" placeholder="Subject" required></div>
                    <div class="form-group"><input type="number" name="marks" class="form-control" placeholder="Marks (0-100)" min="0" max="100" required></div>
                    <div class="form-group"><input type="text" name="exam_type" class="form-control" placeholder="Exam Type (Mid Term/End Term)" required></div>
                    <div class="form-group"><select name="term" class="form-control" required><option value="1">Term 1</option><option value="2">Term 2</option><option value="3">Term 3</option></select></div>
                    <div class="form-group"><button type="submit" name="add_result" class="btn btn-success"><i class="fas fa-plus"></i> Add</button></div>
                </form>
                
                <h4 style="margin-bottom:10px; color:#4a5568;">Bulk Entry (Multiple Subjects)</h4>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group"><select name="student_id" class="form-control" required><option value="">Select Student</option><?php foreach($students as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><select name="exam_type" class="form-control" required><option value="Mid Term">Mid Term</option><option value="End Term">End Term</option><option value="CAT">CAT</option></select></div>
                        <div class="form-group"><select name="term" class="form-control" required><option value="1">Term 1</option><option value="2">Term 2</option><option value="3">Term 3</option></select></div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin:15px 0;">
                        <div class="form-group"><label style="font-size:0.8rem; color:#4a5568;">Subject 1</label><input type="text" name="subjects[]" class="form-control" placeholder="e.g., Mathematics"></div>
                        <div class="form-group"><label style="font-size:0.8rem; color:#4a5568;">Marks</label><input type="number" name="marks[]" class="form-control" placeholder="0-100" min="0" max="100"></div>
                        <div class="form-group"><label style="font-size:0.8rem; color:#4a5568;">Subject 2</label><input type="text" name="subjects[]" class="form-control" placeholder="e.g., English"></div>
                        <div class="form-group"><label style="font-size:0.8rem; color:#4a5568;">Marks</label><input type="number" name="marks[]" class="form-control" placeholder="0-100" min="0" max="100"></div>
                        <div class="form-group"><label style="font-size:0.8rem; color:#4a5568;">Subject 3</label><input type="text" name="subjects[]" class="form-control" placeholder="e.g., Science"></div>
                        <div class="form-group"><label style="font-size:0.8rem; color:#4a5568;">Marks</label><input type="number" name="marks[]" class="form-control" placeholder="0-100" min="0" max="100"></div>
                        <div class="form-group"><label style="font-size:0.8rem; color:#4a5568;">Subject 4</label><input type="text" name="subjects[]" class="form-control" placeholder="e.g., Social Studies"></div>
                        <div class="form-group"><label style="font-size:0.8rem; color:#4a5568;">Marks</label><input type="number" name="marks[]" class="form-control" placeholder="0-100" min="0" max="100"></div>
                    </div>
                    <div class="form-row"><div class="form-group"><button type="submit" name="add_bulk_marks" class="btn btn-primary"><i class="fas fa-save"></i> Save All Marks</button></div></div>
                </form>
            </div>
            
            <h4 style="margin:20px 0 10px; color:#1a1a2e;">Recent Results</h4>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Student</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Exam</th><th>Term</th></tr></thead>
                    <tbody><?php foreach($results as $r): $marks = $r['marks']; $grade = ''; $grade_class = ''; if ($marks >= 80) { $grade = 'A'; $grade_class = 'grade-A'; } elseif ($marks >= 70) { $grade = 'B'; $grade_class = 'grade-B'; } elseif ($marks >= 60) { $grade = 'C'; $grade_class = 'grade-C'; } elseif ($marks >= 50) { $grade = 'D'; $grade_class = 'grade-D'; } else { $grade = 'F'; $grade_class = 'grade-F'; } ?>
                        <tr><td><?= htmlspecialchars($r['name']) ?></td><td><?= htmlspecialchars($r['subject']) ?></td><td><strong><?= $marks ?>%</strong></td><td><span class="grade <?= $grade_class ?>"><?= $grade ?></span></td><td><?= htmlspecialchars($r['exam_type']) ?></td><td>Term <?= $r['term'] ?></td></tr>
                    <?php endforeach; ?></tbody>
                </table>
            </div>
            <?php
        }

        renderPage($page, $pdo);
        ?>
        
        <!-- Footer -->
        <div class="footer">
            <span><i class="fas fa-school"></i> St Jude Bukomansimbi Primary School</span>
            <span>&copy; <?= date('Y') ?> All Rights Reserved</span>
        </div>
        
    </main>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
}
document.querySelectorAll('.sidebar-nav a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 992) {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('active');
        }
    });
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('active');
    }
});
</script>

</body>
</html>