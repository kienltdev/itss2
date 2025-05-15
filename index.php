<?php
// --- SET DEFAULT TIMEZONE ---
date_default_timezone_set('Asia/Ho_Chi_Minh'); // Set timezone to GMT+7

// --- CONFIGURATION ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'task_manager_db');

// --- DATABASE CONNECTION ---
function db_connect() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        error_log("Kết nối thất bại: " . $conn->connect_error);
        // For API requests, return JSON error instead of dying
        if (!empty($_GET['action']) && strpos($_GET['action'], 'notification') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database connection error.']);
            exit;
        }
        die("Không thể kết nối đến cơ sở dữ liệu. Vui lòng thử lại sau.");
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

$conn = db_connect();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- HELPER FUNCTIONS ---
function format_deadline_php($deadlineString) {
    if (!$deadlineString) return 'Chưa có';
    try {
        $date = new DateTime($deadlineString);
        return $date->format('d/m/Y, H:i');
    } catch (Exception $e) {
        return 'Không hợp lệ';
    }
}

function get_priority_class_php($priority) {
    if ($priority === 'high') return 'priority-high';
    if ($priority === 'medium') return 'priority-medium';
    if ($priority === 'low') return 'priority-low';
    return 'bg-slate-100 text-slate-700';
}

function get_priority_text_php($priority) {
    if ($priority === 'high') return 'Cao';
    if ($priority === 'medium') return 'TB';
    if ($priority === 'low') return 'Thấp';
    return 'N/A';
}

function get_task_status_text_php($status) {
    if ($status === 'todo') return 'Chưa làm';
    if ($status === 'inprogress') return 'Đang làm';
    if ($status === 'completed') return 'Đã làm';
    return 'Không rõ';
}

$heroiconsDataPhp = [
    'briefcase' => ["M20.25 6.375c0-.621-.504-1.125-1.125-1.125H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h14.25c.621 0 1.125-.504 1.125-1.125V6.375z", "M16.5 7.875V6.375c0-.621-.504-1.125-1.125-1.125H8.625c-.621 0-1.125.504-1.125 1.125v1.5m5.25 0V6.375m0 1.5H8.25m5.25 0V18m-5.25 0V7.875"],
    'user-group' => ["M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"],
    'academic-cap' => ["M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"],
    'code-bracket' => ["M10.5 6h3m-6.75 6.75 2.25-2.25L6 9l-2.25 2.25L6 13.5l2.25-2.25L10.5 15m3-6h-3m6.75 6.75-2.25-2.25L18 9l2.25 2.25L18 13.5l-2.25-2.25L13.5 15"],
    'tag' => ["M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 12l4.179 2.25M6.429 15l5.571-3 5.571 3m0 0l4.179-2.25L17.75 12l-4.179-2.25m0 0l5.571 3 5.571-3m0 0l4.179 2.25L17.75 12l-4.179 2.25"]
];
$availableIconNamesPhp = array_keys($heroiconsDataPhp);
$reminderValues = [ // Values in SECONDS
    '5m' => 5 * 60, '10m' => 10 * 60, '15m' => 15 * 60, '30m' => 30 * 60,
    '1h' => 60 * 60, '2h' => 2 * 60 * 60, '6h' => 6 * 60 * 60,
    '12h' => 12 * 60 * 60, '1d' => 24 * 60 * 60
];
$colorHex = ['pink' => '#ec4899', 'purple' => '#8b5cf6', 'orange' => '#f97316', 'blue' => '#3b82f6', 'green' => '#22c55e'];

// --- NOTIFICATION GENERATION LOGIC ---
function generate_notifications($conn_param, $reminderValues_param) {
    // IMPORTANT: This function is called on page load for demonstration.
    // In a production environment, this logic should be in a separate script
    // and run periodically by a CRON JOB on your server.

    $now_time = time(); // Current Unix timestamp
    // Select tasks that are not completed and might need notifications
    $sql_tasks_for_notif = "SELECT id, name, deadline, reminder_setting, status, subject_tag FROM main_tasks WHERE status != 'completed'";
    $result_tasks = $conn_param->query($sql_tasks_for_notif);

    if ($result_tasks) {
        while ($task = $result_tasks->fetch_assoc()) {
            if ($task['deadline']) { // Only process tasks with a deadline
                $deadlineTime = strtotime($task['deadline']); // Convert deadline string to Unix timestamp

                // 1. Check for OVERDUE notifications
                // Avoid re-notifying for overdue too frequently (e.g., check if an overdue notification was created in the last day)
                $overdue_notif_exists_sql = "SELECT id FROM notifications WHERE task_id = ? AND type = 'overdue' AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)";
                $stmt_check_overdue = $conn_param->prepare($overdue_notif_exists_sql);
                if ($stmt_check_overdue) {
                    $stmt_check_overdue->bind_param("i", $task['id']);
                    $stmt_check_overdue->execute();
                    $overdue_exists_result = $stmt_check_overdue->get_result();
                    $stmt_check_overdue->close();

                    if ($now_time > $deadlineTime && $overdue_exists_result->num_rows == 0) {
                        // Task is overdue and no recent overdue notification exists
                        $timeDiff_php = $now_time - $deadlineTime;
                        $overdueText_php = "";
                        if ($timeDiff_php < 60) { $overdueText_php = "vài giây"; }
                        else if ($timeDiff_php < 3600) { $overdueText_php = round($timeDiff_php / 60) . " phút"; }
                        else { $overdueText_php = round($timeDiff_php / 3600) . " giờ"; }

                        $message = "Nhiệm vụ '" . htmlspecialchars($task['name']) . "' (Môn: " . htmlspecialchars($task['subject_tag'] ?: 'N/A') . ") đã quá hạn {$overdueText_php}. Hãy hoàn thành ngay!";
                        $stmt_insert_overdue = $conn_param->prepare("INSERT INTO notifications (task_id, type, message, notify_at) VALUES (?, 'overdue', ?, NOW())");
                        if ($stmt_insert_overdue) {
                            $stmt_insert_overdue->bind_param("is", $task['id'], $message);
                            $stmt_insert_overdue->execute();
                            $stmt_insert_overdue->close();
                            error_log("Generated OVERDUE notification for task ID: " . $task['id']);
                        } else {
                            error_log("Error preparing statement for overdue insert: " . $conn_param->error);
                        }
                    }
                } else {
                     error_log("Error preparing statement for overdue check: " . $conn_param->error);
                }


                // 2. Check for REMINDER notifications
                $reminderSetting = $task['reminder_setting'];
                if ($reminderSetting && $reminderSetting !== 'none' && isset($reminderValues_param[$reminderSetting])) {
                    $reminder_offset_seconds = $reminderValues_param[$reminderSetting];
                    $reminderTime = $deadlineTime - $reminder_offset_seconds; // Timestamp when reminder should ideally be shown

                    // Check if a reminder for this task, for this specific deadline and reminder setting, was already created.
                    // We use notify_at to be more precise for reminders.
                    $reminder_notif_exists_sql = "SELECT id FROM notifications WHERE task_id = ? AND type = 'reminder' AND notify_at = ?";
                    $notify_at_dt_check = new DateTime("@$reminderTime"); // Convert timestamp to DateTime
                    $notify_at_formatted_check = $notify_at_dt_check->format('Y-m-d H:i:s'); // Format for DB comparison

                    $stmt_check_reminder = $conn_param->prepare($reminder_notif_exists_sql);
                    if ($stmt_check_reminder) {
                        $stmt_check_reminder->bind_param("is", $task['id'], $notify_at_formatted_check);
                        $stmt_check_reminder->execute();
                        $reminder_exists_result = $stmt_check_reminder->get_result();
                        $stmt_check_reminder->close();

                        // Condition to insert new reminder:
                        // - Current time is at or after the calculated reminder time.
                        // - Current time is still before the actual task deadline.
                        // - No identical reminder (same task, same notify_at time) already exists.
                        if ($now_time >= $reminderTime && $now_time < $deadlineTime && $reminder_exists_result->num_rows == 0) {
                            $message = "Nhắc nhở: Nhiệm vụ '" . htmlspecialchars($task['name']) . "' (Môn: " . htmlspecialchars($task['subject_tag'] ?: 'N/A') . ") sắp đến hạn vào " . format_deadline_php($task['deadline']) . ".";
                            
                            $stmt_insert_reminder = $conn_param->prepare("INSERT INTO notifications (task_id, type, message, notify_at) VALUES (?, 'reminder', ?, ?)");
                            if ($stmt_insert_reminder) {
                                $stmt_insert_reminder->bind_param("iss", $task['id'], $message, $notify_at_formatted_check);
                                $stmt_insert_reminder->execute();
                                $stmt_insert_reminder->close();
                                error_log("Generated REMINDER notification for task ID: " . $task['id'] . " for notify_at: " . $notify_at_formatted_check);
                            } else {
                                error_log("Error preparing statement for reminder insert: " . $conn_param->error);
                            }
                        }
                    } else {
                        error_log("Error preparing statement for reminder check: " . $conn_param->error);
                    }
                }
            }
        }
    } else {
        error_log("Error fetching tasks for notification generation: " . $conn_param->error);
    }
}

// Call notification generation on page load (FOR DEMONSTRATION/TESTING ONLY)
// In a production environment, this should be handled by a CRON JOB.
generate_notifications($conn, $reminderValues);


// --- API Endpoints & POST Actions ---
$action = $_REQUEST['action'] ?? ''; // Use $_REQUEST to catch GET and POST for API

if ($action === 'get_unread_notifications') {
    header('Content-Type: application/json');
    $notifications = [];
    // Fetch notifications that are not read
    $sql = "SELECT id, task_id, type, message, DATE_FORMAT(created_at, '%d/%m/%Y, %H:%i') as formatted_created_at FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        echo json_encode(['success' => true, 'notifications' => $notifications]);
    } else {
        error_log("Lỗi truy vấn get_unread_notifications: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Error fetching notifications.']);
    }
    exit;
} elseif ($action === 'mark_notification_read') {
    header('Content-Type: application/json');
    $notification_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($notification_id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $notification_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                error_log("Lỗi thực thi mark_notification_read: " . $stmt->error);
                echo json_encode(['success' => false, 'message' => 'Error updating notification.']);
            }
            $stmt->close();
        } else {
            error_log("Lỗi chuẩn bị câu lệnh mark_notification_read: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Error preparing statement.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
    }
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add_main_task') {
        $name = $conn->real_escape_string($_POST['mainTaskName']);
        $color = $conn->real_escape_string($_POST['mainTaskColor']);
        $subjectTag = $conn->real_escape_string($_POST['mainTaskSubjectTag']);
        $deadline = !empty($_POST['mainTaskDeadline']) ? $conn->real_escape_string($_POST['mainTaskDeadline']) : NULL;
        $priority = $conn->real_escape_string($_POST['mainTaskPriority']);
        $reminder = $conn->real_escape_string($_POST['mainTaskReminder']);
        $repeat = $conn->real_escape_string($_POST['mainTaskRepeat']);
        $repeatCount = ($repeat !== 'none' && !empty($_POST['mainTaskRepeatCount'])) ? (int)$_POST['mainTaskRepeatCount'] : NULL;
        $iconName = $availableIconNamesPhp[array_rand($availableIconNamesPhp)];

        $stmt = $conn->prepare("INSERT INTO main_tasks (name, color, icon_name, subject_tag, deadline, priority, reminder_setting, repeat_interval, repeat_count, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'todo')");
        if ($stmt) {
            $stmt->bind_param("ssssssssi", $name, $color, $iconName, $subjectTag, $deadline, $priority, $reminder, $repeat, $repeatCount);
            if (!$stmt->execute()) {
                error_log("Lỗi thực thi câu lệnh thêm nhiệm vụ: " . $stmt->error);
            } else {
                // Optionally, create a 'task_created' notification
                $new_task_id = $stmt->insert_id;
                $notif_message = "Nhiệm vụ mới đã được tạo: '" . htmlspecialchars($name) . "'";
                $stmt_notif = $conn->prepare("INSERT INTO notifications (task_id, type, message, notify_at) VALUES (?, 'task_created', ?, NOW())");
                if($stmt_notif){
                    $stmt_notif->bind_param("is", $new_task_id, $notif_message);
                    $stmt_notif->execute();
                    $stmt_notif->close();
                }
            }
            $stmt->close();
        } else {
            error_log("Lỗi chuẩn bị câu lệnh thêm nhiệm vụ: " . $conn->error);
        }
        header("Location: index.php");
        exit;
    } elseif ($action === 'update_task_status') {
        $taskId = (int)$_POST['task_id'];
        $newStatus = $conn->real_escape_string($_POST['status']);
        if (in_array($newStatus, ['todo', 'inprogress', 'completed'])) {
            $stmt = $conn->prepare("UPDATE main_tasks SET status = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $newStatus, $taskId);
                if(!$stmt->execute()){
                    error_log("Lỗi thực thi cập nhật trạng thái: " . $stmt->error);
                }
                $stmt->close();
            } else {
                 error_log("Lỗi chuẩn bị câu lệnh cập nhật trạng thái: " . $conn->error);
            }
        }
        header("Location: index.php");
        exit;
    } elseif ($action === 'delete_task') {
        $taskId = (int)$_POST['task_id'];
        // Delete related notifications first
        $stmt_del_notif = $conn->prepare("DELETE FROM notifications WHERE task_id = ?");
        if($stmt_del_notif){
            $stmt_del_notif->bind_param("i", $taskId);
            $stmt_del_notif->execute();
            $stmt_del_notif->close();
        } else {
            error_log("Lỗi chuẩn bị câu lệnh xóa notifications: " . $conn->error);
        }

        $stmt = $conn->prepare("DELETE FROM main_tasks WHERE id = ?");
         if ($stmt) {
            $stmt->bind_param("i", $taskId);
            if(!$stmt->execute()){
                error_log("Lỗi thực thi xóa nhiệm vụ: " . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log("Lỗi chuẩn bị câu lệnh xóa nhiệm vụ: " . $conn->error);
        }
        header("Location: index.php");
        exit;
    } elseif ($action === 'add_sub_task') {
        $mainTaskId = (int)$_POST['main_task_id'];
        $subtaskName = $conn->real_escape_string($_POST['sub_task_name']);

        $stmt = $conn->prepare("INSERT INTO sub_tasks (main_task_id, name, status) VALUES (?, ?, 'todo')");
        if ($stmt) {
            $stmt->bind_param("is", $mainTaskId, $subtaskName);
            if(!$stmt->execute()){
                error_log("Lỗi thực thi thêm nhiệm vụ con: " . $stmt->error);
            }
            $stmt->close();
        } else {
            error_log("Lỗi chuẩn bị câu lệnh thêm nhiệm vụ con: " . $conn->error);
        }
        header("Location: index.php?view=task_details&id=" . $mainTaskId);
        exit;
    } elseif ($action === 'toggle_subtask_status') {
        $subtaskId = (int)$_POST['subtask_id'];
        $mainTaskId = (int)$_POST['main_task_id_for_redirect'];
        $currentStatus = 'todo';
        $stmt_get = $conn->prepare("SELECT status FROM sub_tasks WHERE id = ?");
        if($stmt_get) {
            $stmt_get->bind_param("i", $subtaskId);
            $stmt_get->execute();
            $result_get = $stmt_get->get_result();
            if ($row_get = $result_get->fetch_assoc()) {
                $currentStatus = $row_get['status'];
            }
            $stmt_get->close();
        }

        $newStatus = ($currentStatus === 'completed') ? 'todo' : 'completed';
        $stmt_update = $conn->prepare("UPDATE sub_tasks SET status = ? WHERE id = ?");
         if ($stmt_update) {
            $stmt_update->bind_param("si", $newStatus, $subtaskId);
            if(!$stmt_update->execute()){
                error_log("Lỗi thực thi cập nhật trạng thái nhiệm vụ con: " . $stmt_update->error);
            }
            $stmt_update->close();
        } else {
            error_log("Lỗi chuẩn bị câu lệnh cập nhật trạng thái nhiệm vụ con: " . $conn->error);
        }
        header("Location: index.php?view=task_details&id=" . $mainTaskId);
        exit;
    }
}


// --- VIEW LOGIC (GET requests) ---
$view = $_GET['view'] ?? 'dashboard';

$main_tasks_list = [];
$filter_condition = "";
$current_filter = $_GET['filter'] ?? 'all';

if ($current_filter !== 'all') {
    $filter_status = $conn->real_escape_string($current_filter);
    $filter_condition = " AND mt.status = '$filter_status'";
}

$sql_main_tasks = "SELECT mt.*, (SELECT COUNT(*) FROM sub_tasks st WHERE st.main_task_id = mt.id) as subtask_count
                   FROM main_tasks mt
                   WHERE 1=1 $filter_condition
                   ORDER BY CASE WHEN mt.deadline IS NULL THEN 1 ELSE 0 END, mt.deadline ASC, FIELD(mt.priority, 'high', 'medium', 'low') DESC, mt.created_at DESC";
$result_main_tasks = $conn->query($sql_main_tasks);
if ($result_main_tasks) {
    while ($row = $result_main_tasks->fetch_assoc()) {
        $main_tasks_list[] = $row;
    }
} else {
    error_log("Lỗi truy vấn main_tasks: " . $conn->error);
}

// Logic for "Today's Tasks"
$today_obj = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
$today_start = $today_obj->format('Y-m-d 00:00:00');
$today_end = $today_obj->format('Y-m-d 23:59:59');


$sql_today_total = "SELECT COUNT(*) as total FROM main_tasks WHERE deadline >= '$today_start' AND deadline <= '$today_end'";
$sql_today_completed = "SELECT COUNT(*) as completed FROM main_tasks WHERE deadline >= '$today_start' AND deadline <= '$today_end' AND status = 'completed'";

$total_today_tasks = 0;
$completed_today_tasks = 0;

$res_total_today = $conn->query($sql_today_total);
if ($res_total_today && $row_total = $res_total_today->fetch_assoc()) {
    $total_today_tasks = $row_total['total'];
} else {
    error_log("Lỗi truy vấn total_today_tasks: " . $conn->error);
}
$res_completed_today = $conn->query($sql_today_completed);
if ($res_completed_today && $row_completed = $res_completed_today->fetch_assoc()) {
    $completed_today_tasks = $row_completed['completed'];
} else {
     error_log("Lỗi truy vấn completed_today_tasks: " . $conn->error);
}


$today_progress_percentage_val = 0;
$today_progress_text_val = "Không có nhiệm vụ nào hôm nay.";
if ($total_today_tasks > 0) {
    $today_progress_percentage_val = round(($completed_today_tasks / $total_today_tasks) * 100);
    $today_progress_text_val = "Đã hoàn thành $completed_today_tasks/$total_today_tasks nhiệm vụ hôm nay.";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giao diện Bảng điều khiển Nhiệm vụ</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f2f5; }
        .color-option.selected { box-shadow: 0 0 0 2px white, 0 0 0 4px currentColor; }
        .filter-button.active { background-color: #4f46e5; color: white; }
        .subtask-item-checkbox:checked + span { text-decoration: line-through; color: #6b7280; }
        .priority-tag { padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 500; text-align: center; }
        .priority-high { background-color: #fee2e2; color: #b91c1c; }
        .priority-medium { background-color: #ffedd5; color: #c2410c; }
        .priority-low { background-color: #dbeafe; color: #1d4ed8; }
        .task-menu { position: absolute; right: 0; top: 100%; background-color: white; border-radius: 0.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); z-index: 10; width: max-content; padding-top: 0.25rem; padding-bottom: 0.25rem; }
        .task-menu-item { display: block; padding: 0.5rem 1rem; font-size: 0.875rem; color: #374151; cursor: pointer; }
        .task-menu-item:hover { background-color: #f3f4f6; }
        .task-menu-item-delete:hover { background-color: #fee2e2; color: #b91c1c; }
        #notificationContainer { position: fixed; top: 1rem; left: 50%; transform: translateX(-50%); z-index: 1000; width: 380px; max-width: 90%; display: flex; flex-direction: column; gap: 0.75rem; }
        .notification { background-color: white; color: #1f2937; padding: 1rem; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); display: flex; align-items: flex-start; gap: 0.75rem; opacity: 0; transform: translateY(-20px); animation: slideDown 0.5s forwards, fadeOut 0.5s 9.5s forwards; }
        .notification.show { opacity: 1; transform: translateY(0); }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; transform: translateY(-5px); } }
        .notification-icon { flex-shrink: 0; width: 2rem; height: 2rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; }
        .notification-icon.error { background-color: #fee2e2; } .notification-icon.error svg { color: #ef4444; }
        .notification-icon.success { background-color: #dcfce7; } .notification-icon.success svg { color: #22c55e; }
        .notification-icon.info { background-color: #dbeafe; } .notification-icon.info svg { color: #3b82f6; }
        .notification-icon.general { background-color: #e5e7eb; } .notification-icon.general svg { color: #4b5563; } /* Added general style */
        .notification-content h4 { font-weight: 600; margin-bottom: 0.25rem; }
        .notification-content p { font-size: 0.875rem; color: #4b5563; }
        .notification-close { margin-left: auto; padding: 0.25rem; border-radius: 9999px; background-color: transparent; border: none; cursor: pointer; color: #9ca3af; }
        .notification-close:hover { color: #374151; background-color: #f3f4f6; }
        .task-overdue .task-name-display {
            color: #ef4444;
            font-weight: 600;
        }
        .input-style { /* Added for consistency in modal inputs */
            border: 1px solid #cbd5e1;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
            width: 100%;
        }
        .input-style:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            border-color: #6366f1; /* indigo-500 */
            box-shadow: 0 0 0 2px #a5b4fc; /* indigo-300 */
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">
    <div id="notificationContainer">
    </div>

    <div class="container mx-auto p-4 md:p-8 max-w-2xl">

        <div id="mainDashboardView" <?php if ($view !== 'dashboard') echo 'class="hidden"'; ?>>
            <header class="flex items-center justify-between mb-8">
                <div class="flex items-center space-x-3">
                    <img src="https://placehold.co/48x48/7C3AED/FFFFFF?text=ACK&font=Inter" alt="Ảnh đại diện ACK" class="w-12 h-12 rounded-full border-2 border-white shadow-md">
                    <div>
                        <p class="text-sm text-slate-600">Xin chào</p>
                        <h1 class="text-xl font-semibold text-slate-900">ACK</h1>
                    </div>
                </div>
                <button aria-label="Thông báo" class="p-2 rounded-full hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-slate-500"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 00-5.714 0" /></svg>
                </button>
            </header>

            <section class="bg-gradient-to-br from-indigo-600 to-purple-700 text-white p-6 rounded-2xl shadow-xl mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-medium">Nhiệm vụ hôm nay của bạn</h2>
                        <p id="todayTaskProgressText" class="text-sm opacity-80"><?php echo htmlspecialchars($today_progress_text_val); ?></p>
                    </div>
                    <button aria-label="Tùy chọn nhiệm vụ chính" class="p-1 rounded-full hover:bg-white/20 focus:outline-none">
                         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
                    </button>
                </div>
                <div class="flex items-center justify-between mt-6">
                    <div class="relative w-24 h-24">
                         <svg class="w-full h-full" viewBox="0 0 36 36">
                            <path class="progress-ring__circle--bg" stroke-width="3.8" fill="none" stroke="#A5B4FC" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <path id="todayProgressCircle" class="progress-ring__circle--fg" stroke-width="3.8" fill="none" stroke-dasharray="<?php echo $today_progress_percentage_val; ?>, 100" stroke-linecap="round" stroke="#FFFFFF" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        </svg>
                        <div id="todayProgressPercentage" class="absolute inset-0 flex items-center justify-center text-2xl font-bold"><?php echo $today_progress_percentage_val; ?>%</div>
                    </div>
                    <a href="index.php?view=today" id="viewTodayTasksButtonPHP" class="bg-white text-indigo-600 font-semibold py-3 px-6 rounded-lg shadow-md hover:bg-slate-100 transition duration-150 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-75">
                        Xem Nhiệm vụ
                    </a>
                </div>
            </section>

            <section>
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center space-x-2">
                        <h2 class="text-xl font-semibold text-slate-900">Nhiệm vụ</h2>
                        <span id="mainTaskCount" class="text-xs font-medium bg-indigo-100 text-indigo-700 px-2 py-1 rounded-full"><?php echo count($main_tasks_list); ?></span>
                    </div>
                    <button id="openAddMainTaskModalButton" aria-label="Thêm nhiệm vụ mới" class="p-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </button>
                </div>
                <div class="mb-6 flex flex-wrap gap-2">
                    <a href="index.php?filter=all" class="filter-button <?php if ($current_filter === 'all') echo 'active'; ?> text-sm font-medium py-2 px-4 rounded-lg bg-indigo-100 text-indigo-700 hover:bg-indigo-200 focus:outline-none">Tất cả</a>
                    <a href="index.php?filter=todo" class="filter-button <?php if ($current_filter === 'todo') echo 'active'; ?> text-sm font-medium py-2 px-4 rounded-lg bg-indigo-100 text-indigo-700 hover:bg-indigo-200 focus:outline-none">Chưa làm</a>
                    <a href="index.php?filter=inprogress" class="filter-button <?php if ($current_filter === 'inprogress') echo 'active'; ?> text-sm font-medium py-2 px-4 rounded-lg bg-indigo-100 text-indigo-700 hover:bg-indigo-200 focus:outline-none">Đang làm</a>
                    <a href="index.php?filter=completed" class="filter-button <?php if ($current_filter === 'completed') echo 'active'; ?> text-sm font-medium py-2 px-4 rounded-lg bg-indigo-100 text-indigo-700 hover:bg-indigo-200 focus:outline-none">Đã làm</a>
                </div>

                <div id="mainTasksContainer" class="space-y-4">
                    <?php if (empty($main_tasks_list)): ?>
                        <p class="text-slate-500 text-center py-4">Không có nhiệm vụ nào phù hợp.</p>
                    <?php else: ?>
                        <?php foreach ($main_tasks_list as $task):
                            $is_overdue = false;
                            if ($task['deadline'] && time() > strtotime($task['deadline']) && $task['status'] !== 'completed') {
                                $is_overdue = true;
                            }
                            $icon_paths = $heroiconsDataPhp[$task['icon_name']] ?? $heroiconsDataPhp['tag'];
                            $paths_svg = '';
                            foreach($icon_paths as $path_data) {
                                $paths_svg .= "<path stroke-linecap='round' stroke-linejoin='round' d='{$path_data}' />";
                            }
                            $icon_svg = "<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-8 h-8 text-{$task['color']}-500'>{$paths_svg}</svg>";
                        ?>
                        <div class="bg-white p-4 rounded-xl shadow-lg flex items-start justify-between <?php if($is_overdue) echo 'task-overdue'; ?>" data-main-task-id="<?php echo $task['id']; ?>">
                            <div class="flex items-start space-x-4 flex-grow">
                                <div class="mt-1 bg-<?php echo htmlspecialchars($task['color']); ?>-100 p-2 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <?php echo $icon_svg; ?>
                                </div>
                                <a href="index.php?view=task_details&id=<?php echo $task['id']; ?>" class="flex-grow cursor-pointer">
                                    <h3 class="font-semibold text-slate-800 text-lg task-name-display <?php if($is_overdue) echo 'text-red-500 font-bold'; ?>"><?php echo htmlspecialchars($task['name']); ?></h3>
                                    <p class="text-xs text-slate-500 mb-1.5"><?php echo $task['subtask_count']; ?> nhiệm vụ con &bull; <span class="font-medium text-<?php echo htmlspecialchars($task['color']); ?>-600"><?php echo htmlspecialchars($task['subject_tag'] ?: 'Chưa có môn'); ?></span></p>
                                    <div class="flex items-center space-x-2 text-xs mb-1.5">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-500"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span><?php echo format_deadline_php($task['deadline']); ?></span>
                                    </div>
                                    <div class="flex items-center space-x-2 text-xs">
                                         <span class="priority-tag <?php echo get_priority_class_php($task['priority']); ?>"><?php echo get_priority_text_php($task['priority']); ?></span>
                                         <span class="px-2 py-0.5 text-xs font-medium rounded-full <?php echo $task['status'] === 'completed' ? 'bg-green-100 text-green-700' : ($task['status'] === 'inprogress' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-700'); ?>">
                                            <?php echo get_task_status_text_php($task['status']); ?>
                                        </span>
                                    </div>
                                </a>
                            </div>
                            <div class="relative flex-shrink-0">
                                <button class="task-menu-button p-2 text-slate-500 hover:text-slate-700 focus:outline-none" data-task-id="<?php echo $task['id']; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z" /></svg>
                                </button>
                                <div class="task-menu hidden" id="menu-<?php echo $task['id']; ?>">
                                    <form action="index.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_task_status">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="status" value="todo">
                                        <button type="submit" class="task-menu-item w-full text-left">Chưa làm</button>
                                    </form>
                                     <form action="index.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_task_status">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="status" value="inprogress">
                                        <button type="submit" class="task-menu-item w-full text-left">Đang làm</button>
                                    </form>
                                     <form action="index.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="update_task_status">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <input type="hidden" name="status" value="completed">
                                        <button type="submit" class="task-menu-item w-full text-left">Đã hoàn thành</button>
                                    </form>
                                    <hr class="my-1">
                                    <form action="index.php" method="POST" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhiệm vụ này không?');">
                                        <input type="hidden" name="action" value="delete_task">
                                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                        <button type="submit" class="task-menu-item task-menu-item-delete w-full text-left">Xóa nhiệm vụ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
            </div>

        <div id="subtaskDetailView" <?php if ($view !== 'task_details') echo 'class="hidden"'; ?>>
            <?php
            if ($view === 'task_details') {
                // ... PHP logic for subtask view ...
                $current_main_task_id_get = (int)$_GET['id'];
                $stmt_main = $conn->prepare("SELECT * FROM main_tasks WHERE id = ?");
                $stmt_main->bind_param("i", $current_main_task_id_get);
                $stmt_main->execute();
                $result_main = $stmt_main->get_result();
                $current_main_task_details = $result_main->fetch_assoc();
                $stmt_main->close();

                if ($current_main_task_details) {
                    echo "<header class='flex items-center justify-between mb-6'>";
                    echo "<a href='index.php' class='p-2 rounded-full hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-6 h-6 text-slate-700'><path stroke-linecap='round' stroke-linejoin='round' d='M15.75 19.5L8.25 12l7.5-7.5' /></svg></a>";
                    echo "<h2 class='text-xl font-semibold text-slate-900 text-center flex-grow'>" . htmlspecialchars($current_main_task_details['name']) . "</h2>";
                    echo "<div class='w-8'></div></header>";

                    echo "<div class='mb-4 flex space-x-2'>";
                    echo "<form action='index.php' method='POST' class='flex-grow flex space-x-2'>";
                    echo "<input type='hidden' name='action' value='add_sub_task'>";
                    echo "<input type='hidden' name='main_task_id' value='{$current_main_task_id_get}'>";
                    echo "<input type='text' name='sub_task_name' placeholder='Thêm nhiệm vụ con mới...' class='flex-grow mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm'>";
                    echo "<button type='submit' class='p-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2' stroke='currentColor' class='w-5 h-5'><path stroke-linecap='round' stroke-linejoin='round' d='M12 4.5v15m7.5-7.5h-15' /></svg></button>";
                    echo "</form></div>";

                    echo "<div id='subtaskListContainerPHP' class='space-y-3'>";
                    $stmt_subtasks = $conn->prepare("SELECT * FROM sub_tasks WHERE main_task_id = ? ORDER BY created_at ASC");
                    $stmt_subtasks->bind_param("i", $current_main_task_id_get);
                    $stmt_subtasks->execute();
                    $result_subtasks = $stmt_subtasks->get_result();
                    if ($result_subtasks->num_rows > 0) {
                        while($subtask = $result_subtasks->fetch_assoc()) {
                            $is_completed_sub = $subtask['status'] === 'completed';
                            echo "<div class='bg-white p-3 rounded-lg shadow flex items-center justify-between'>";
                            echo "<div class='flex items-center'>";
                            echo "<form action='index.php' method='POST' class='inline-block mr-3'>";
                            echo "<input type='hidden' name='action' value='toggle_subtask_status'>";
                            echo "<input type='hidden' name='subtask_id' value='{$subtask['id']}'>";
                            echo "<input type='hidden' name='main_task_id_for_redirect' value='{$current_main_task_id_get}'>";
                            echo "<input type='checkbox' id='subtask-{$subtask['id']}' class='subtask-item-checkbox h-5 w-5 text-indigo-600 border-slate-300 rounded focus:ring-indigo-500' " . ($is_completed_sub ? 'checked' : '') . " onchange='this.form.submit()'>";
                            echo "</form>";
                            echo "<label for='subtask-{$subtask['id']}' class='text-slate-700 text-sm " . ($is_completed_sub ? 'line-through text-slate-500' : '') . "'>" . htmlspecialchars($subtask['name']) . "</label>";
                            echo "</div>";
                            echo "<span class='px-2 py-0.5 text-xs font-medium rounded-full " . ($subtask['status'] === 'completed' ? 'bg-green-100 text-green-700' : ($subtask['status'] === 'inprogress' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-700')) . "'>" . get_task_status_text_php($subtask['status']) . "</span>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p class='text-slate-500 text-center py-4'>Chưa có nhiệm vụ con nào cho nhiệm vụ này.</p>";
                    }
                    $stmt_subtasks->close();
                    echo "</div>";

                } else {
                    echo "<p class='text-red-500'>Không tìm thấy nhiệm vụ.</p>";
                }
            }
            ?>
            </div>

        <div id="todaysTasksView" <?php if ($view !== 'today') echo 'class="hidden"'; ?>>
            <header class="flex items-center justify-between mb-6">
                <a href="index.php" id="backToDashboardFromTodayButtonPHP" class="p-2 rounded-full hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-slate-700"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </a>
                <h2 class="text-xl font-semibold text-slate-900 text-center flex-grow">Nhiệm vụ Hôm Nay</h2>
                <div class="w-8"></div>
            </header>
            <div id="todaysTasksListContainer" class="space-y-4">
                <?php
                if ($view === 'today') {
                    // ... PHP logic for today's tasks view ...
                    $today_start_q = date('Y-m-d 00:00:00');
                    $today_end_q = date('Y-m-d 23:59:59');
                    $sql_today_list = "SELECT mt.*, (SELECT COUNT(*) FROM sub_tasks st WHERE st.main_task_id = mt.id) as subtask_count
                                       FROM main_tasks mt
                                       WHERE mt.deadline >= '$today_start_q' AND mt.deadline <= '$today_end_q'
                                       ORDER BY mt.deadline ASC, FIELD(mt.priority, 'high', 'medium', 'low')";
                    $result_today_list = $conn->query($sql_today_list);
                    if ($result_today_list && $result_today_list->num_rows > 0) {
                        while ($task_today = $result_today_list->fetch_assoc()) {
                            $is_overdue_today = false;
                            if ($task_today['deadline'] && time() > strtotime($task_today['deadline']) && $task_today['status'] !== 'completed') {
                                $is_overdue_today = true;
                            }
                            $icon_paths_today = $heroiconsDataPhp[$task_today['icon_name']] ?? $heroiconsDataPhp['tag'];
                            $paths_svg_today = '';
                            foreach($icon_paths_today as $path_data_today) {
                                $paths_svg_today .= "<path stroke-linecap='round' stroke-linejoin='round' d='{$path_data_today}' />";
                            }
                            $icon_svg_today = "<svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-8 h-8 text-{$task_today['color']}-500'>{$paths_svg_today}</svg>";

                            echo "<div class='bg-white p-4 rounded-xl shadow-lg flex items-start justify-between " . ($is_overdue_today ? 'task-overdue' : '') . "' data-main-task-id='{$task_today['id']}'>";
                            echo "<div class='flex items-start space-x-4 flex-grow'>";
                            echo "<div class='mt-1 bg-" . htmlspecialchars($task_today['color']) . "-100 p-2 rounded-lg flex items-center justify-center flex-shrink-0'>{$icon_svg_today}</div>";
                            echo "<a href='index.php?view=task_details&id={$task_today['id']}' class='flex-grow cursor-pointer'>";
                            echo "<h3 class='font-semibold text-slate-800 text-lg task-name-display " . ($is_overdue_today ? 'text-red-500 font-bold' : '') . "'>" . htmlspecialchars($task_today['name']) . "</h3>";
                            echo "<p class='text-xs text-slate-500 mb-1.5'>{$task_today['subtask_count']} nhiệm vụ con &bull; <span class='font-medium text-" . htmlspecialchars($task_today['color']) . "-600'>" . htmlspecialchars($task_today['subject_tag'] ?: 'Chưa có môn') . "</span></p>";
                            echo "<div class='flex items-center space-x-2 text-xs mb-1.5'><svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-4 h-4 text-slate-500'><path stroke-linecap='round' stroke-linejoin='round' d='M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' /></svg><span>" . format_deadline_php($task_today['deadline']) . "</span></div>";
                            echo "<div class='flex items-center space-x-2 text-xs'><span class='priority-tag " . get_priority_class_php($task_today['priority']) . "'>" . get_priority_text_php($task_today['priority']) . "</span><span class='px-2 py-0.5 text-xs font-medium rounded-full " . ($task_today['status'] === 'completed' ? 'bg-green-100 text-green-700' : ($task_today['status'] === 'inprogress' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-700')) . "'>" . get_task_status_text_php($task_today['status']) . "</span></div>";
                            echo "</a></div>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p class='text-slate-500 text-center py-4'>Không có nhiệm vụ nào cho hôm nay.</p>";
                    }
                }
                ?>
            </div>
            </div>

    </div>
    <div id="addMainTaskModal" class="fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full flex items-center justify-center hidden z-50">
        <div class="bg-white p-6 md:p-8 rounded-xl shadow-2xl w-full max-w-lg mx-4 transform transition-all">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-slate-900">Thêm Nhiệm vụ Mới</h3>
                <button id="closeMainTaskModalButton" aria-label="Đóng modal" class="text-slate-400 hover:text-slate-600">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <form id="addMainTaskForm" action="index.php" method="POST">
                <input type="hidden" name="action" value="add_main_task">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="mainTaskNameModal" class="block text-sm font-medium text-slate-700 mb-1">Tên Nhiệm vụ</label>
                        <input type="text" id="mainTaskNameModal" name="mainTaskName" required class="mt-1 block w-full input-style" placeholder="Ví dụ: Hoàn thành Dự án X">
                    </div>
                    <div>
                        <label for="mainTaskSubjectTagModal" class="block text-sm font-medium text-slate-700 mb-1">Môn học</label>
                        <input type="text" id="mainTaskSubjectTagModal" name="mainTaskSubjectTag" class="mt-1 block w-full input-style" placeholder="Ví dụ: Toán, Lập trình Web">
                    </div>
                    <div>
                        <label for="mainTaskDeadlineModal" class="block text-sm font-medium text-slate-700 mb-1">Hạn nộp</label>
                        <input type="datetime-local" id="mainTaskDeadlineModal" name="mainTaskDeadline" class="mt-1 block w-full input-style">
                    </div>
                    <div>
                        <label for="mainTaskPriorityModal" class="block text-sm font-medium text-slate-700 mb-1">Mức độ ưu tiên</label>
                        <select id="mainTaskPriorityModal" name="mainTaskPriority" class="mt-1 block w-full input-style">
                            <option value="low">Thấp</option>
                            <option value="medium" selected>Trung bình</option>
                            <option value="high">Cao</option>
                        </select>
                    </div>
                    <div>
                        <label for="mainTaskReminderModal" class="block text-sm font-medium text-slate-700 mb-1">Nhắc nhở sớm</label>
                        <select id="mainTaskReminderModal" name="mainTaskReminder" class="mt-1 block w-full input-style">
                            <option value="none">Không nhắc nhở</option>
                            <option value="5m">5 phút trước</option>
                            <option value="10m">10 phút trước</option>
                            <option value="15m">15 phút trước</option>
                            <option value="30m">30 phút trước</option>
                            <option value="1h">1 giờ trước</option>
                            <option value="2h">2 giờ trước</option>
                            <option value="6h">6 giờ trước</option>
                            <option value="12h">12 giờ trước</option>
                            <option value="1d">1 ngày trước</option>
                        </select>
                    </div>
                     <div>
                        <label for="mainTaskRepeatModal" class="block text-sm font-medium text-slate-700 mb-1">Lặp lại</label>
                        <select id="mainTaskRepeatModal" name="mainTaskRepeat" class="mt-1 block w-full input-style">
                            <option value="none">Không lặp lại</option>
                            <option value="hourly">Mỗi giờ</option>
                            <option value="daily">Mỗi ngày</option>
                            <option value="weekly">Mỗi tuần</option>
                            <option value="monthly">Mỗi tháng</option>
                        </select>
                    </div>
                    <div id="repeatCountContainerModal" class="hidden md:col-span-2">
                        <label for="mainTaskRepeatCountModal" class="block text-sm font-medium text-slate-700 mb-1">Kết thúc lặp sau (số lần)</label>
                        <input type="number" id="mainTaskRepeatCountModal" name="mainTaskRepeatCount" min="1" class="mt-1 block w-full input-style" placeholder="Ví dụ: 5">
                    </div>
                </div>

                <div class="mt-6 mb-6">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Chọn Màu</label>
                    <div class="flex space-x-2" id="colorOptionsModal">
                        </div>
                    <input type="hidden" id="mainTaskColorModal" name="mainTaskColor" value="pink">
                </div>
                <div class="flex justify-end space-x-3 border-t pt-4 mt-4">
                    <button type="button" id="cancelMainTaskModalButtonPHP" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">Hủy</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Thêm Nhiệm vụ</button>
                </div>
            </form>
        </div>
        </div>

    <script>
        // --- START: MODAL and UI Elements Logic (largely unchanged) ---
        const mainDashboardViewJS = document.getElementById('mainDashboardView');
        const subtaskDetailViewJS = document.getElementById('subtaskDetailView');
        const todaysTasksViewJS = document.getElementById('todaysTasksView');
        const addMainTaskModalJS = document.getElementById('addMainTaskModal');
        const openModalButtonJS = document.getElementById('openAddMainTaskModalButton');
        const closeModalButtonJS = document.getElementById('closeMainTaskModalButton');
        const cancelModalButtonJS = document.getElementById('cancelMainTaskModalButtonPHP');
        const addMainTaskFormJS = document.getElementById('addMainTaskForm');
        const mainTaskRepeatSelectJS = document.getElementById('mainTaskRepeatModal');
        const repeatCountContainerJS = document.getElementById('repeatCountContainerModal');
        const colorOptionsContainerJS = document.getElementById('colorOptionsModal');

        function openMainTaskModalJS() {
            if(addMainTaskModalJS) addMainTaskModalJS.classList.remove('hidden');
            if(mainTaskRepeatSelectJS && repeatCountContainerJS) {
                repeatCountContainerJS.classList.toggle('hidden', mainTaskRepeatSelectJS.value === 'none');
            }
        }
        function closeMainTaskModalJS() {
            if(addMainTaskModalJS) {
                addMainTaskModalJS.classList.add('hidden');
                if(addMainTaskFormJS) addMainTaskFormJS.reset();
                const defaultColor = 'pink';
                if(document.getElementById('mainTaskColorModal')) document.getElementById('mainTaskColorModal').value = defaultColor;
                if(colorOptionsContainerJS){
                    const colorBtns = colorOptionsContainerJS.querySelectorAll('.color-option');
                    colorBtns.forEach(btn => {
                        btn.classList.remove('selected', 'ring-2', 'ring-offset-2');
                        Object.keys(<?php echo json_encode($colorHex ?? []); ?>).forEach(colorName => btn.classList.remove(`ring-${colorName}-500`));
                        if (btn.dataset.color === defaultColor) {
                             btn.classList.add('selected', 'ring-2', 'ring-offset-2', `ring-${defaultColor}-500`);
                        }
                    });
                }
                if(mainTaskRepeatSelectJS) mainTaskRepeatSelectJS.value = 'none';
                if(repeatCountContainerJS) repeatCountContainerJS.classList.add('hidden');
                if(document.getElementById('mainTaskRepeatCountModal')) document.getElementById('mainTaskRepeatCountModal').value = '';
            }
        }

        if(openModalButtonJS) openModalButtonJS.addEventListener('click', openMainTaskModalJS);
        if(closeModalButtonJS) closeModalButtonJS.addEventListener('click', closeMainTaskModalJS);
        if(cancelModalButtonJS) cancelModalButtonJS.addEventListener('click', closeMainTaskModalJS);
        if(addMainTaskModalJS) addMainTaskModalJS.addEventListener('click', (event) => {
            if (event.target === addMainTaskModalJS) closeMainTaskModalJS();
        });

        const colors = <?php echo json_encode($colorHex ?? []); ?>;
        if (colorOptionsContainerJS && Object.keys(colors).length > 0) {
            colorOptionsContainerJS.innerHTML = '';
            Object.keys(colors).forEach(colorName => {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.color = colorName;
                button.style.backgroundColor = colors[colorName];
                button.className = 'color-option w-8 h-8 rounded-full border-2 border-transparent focus:outline-none';
                if (document.getElementById('mainTaskColorModal') && colorName === document.getElementById('mainTaskColorModal').value) {
                    button.classList.add('selected', 'ring-2', 'ring-offset-2', `ring-${colorName}-500`);
                }
                button.addEventListener('click', () => {
                    if(document.getElementById('mainTaskColorModal')) document.getElementById('mainTaskColorModal').value = colorName;
                    colorOptionsContainerJS.querySelectorAll('.color-option').forEach(btn => {
                         btn.classList.remove('selected', 'ring-2', 'ring-offset-2');
                         Object.keys(colors).forEach(cn => btn.classList.remove(`ring-${cn}-500`));
                    });
                    button.classList.add('selected', 'ring-2', 'ring-offset-2', `ring-${colorName}-500`);
                });
                colorOptionsContainerJS.appendChild(button);
            });
        }

        if (mainTaskRepeatSelectJS && repeatCountContainerJS) {
            mainTaskRepeatSelectJS.addEventListener('change', function() {
                repeatCountContainerJS.classList.toggle('hidden', this.value === 'none');
                if (this.value === 'none' && document.getElementById('mainTaskRepeatCountModal')) {
                     document.getElementById('mainTaskRepeatCountModal').value = '';
                }
            });
        }

         function addThreeDotMenuListenersJS() {
             document.querySelectorAll('.task-menu-button').forEach(button => {
                 button.addEventListener('click', (event) => {
                     event.stopPropagation();
                     const taskId = button.dataset.taskId;
                     const menu = document.getElementById(`menu-${taskId}`);
                     document.querySelectorAll('.task-menu').forEach(m => {
                         if (m.id !== `menu-${taskId}`) m.classList.add('hidden');
                     });
                     if(menu) menu.classList.toggle('hidden');
                 });
             });
         }
         addThreeDotMenuListenersJS();

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.task-menu-button') && !event.target.closest('.task-menu')) {
                document.querySelectorAll('.task-menu').forEach(menu => menu.classList.add('hidden'));
            }
        });
        // --- END: MODAL and UI Elements Logic ---


        // --- START: New Notification Logic (Fetching from Backend) ---
        const notificationContainerEl = document.getElementById('notificationContainer');
        const notificationCheckInterval = 30000; // Check every 30 seconds (30000 milliseconds)
        let displayedNotificationIds = new Set(); // Keep track of DB IDs of notifications shown in current page session

        function showNotificationJS(id, title, message, type = 'info', created_at_str = '') {
            if (!notificationContainerEl) return;

            const domNotificationId = `notif-dom-${id}`; // Unique ID for the DOM element using DB notification ID
            
            // If this specific notification (by DB ID) is already in the DOM, don't re-add it.
            // This prevents visual duplication if fetchUnreadNotifications runs multiple times
            // before a notification is closed or auto-removed.
            if (document.getElementById(domNotificationId)) {
                return; 
            }

            const notificationDiv = document.createElement('div');
            notificationDiv.id = domNotificationId;
            notificationDiv.className = 'notification';
            notificationDiv.dataset.notificationDbId = id; // Store original DB ID for marking as read

            let iconSvg = '';
            let iconClass = 'general'; // Default to general

            // Map DB notification types to CSS classes and icons
            if (type === 'error' || type === 'overdue') {
                iconClass = 'error';
                iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>`;
            } else if (type === 'success' || type === 'task_created') {
                iconClass = 'success';
                iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
            } else if (type === 'info' || type === 'reminder') {
                 iconClass = 'info';
                 iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>`;
            } else { // general or other types
                 iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg>`;
            }
            // Use the provided title, or generate one from the type
            const titleText = title || (type.charAt(0).toUpperCase() + type.slice(1).replace('_', ' '));

            notificationDiv.innerHTML = `
                <div class="notification-icon ${iconClass}"> ${iconSvg} </div>
                <div class="notification-content">
                    <h4>${titleText}</h4>
                    <p>${message}</p>
                    ${created_at_str ? `<p class="text-xs text-slate-400 mt-1">Tạo lúc: ${created_at_str}</p>` : ''}
                </div>
                <button class="notification-close" aria-label="Đóng thông báo">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            `;
            notificationContainerEl.prepend(notificationDiv); // Add to the top of the container
            
            // Add to set of displayed DB IDs for this session to avoid re-adding to DOM if fetched again
            displayedNotificationIds.add(id); 

            notificationDiv.querySelector('.notification-close').addEventListener('click', () => {
                markNotificationAsRead(id); // Mark as read in DB
                notificationDiv.remove(); // Remove from DOM
                displayedNotificationIds.delete(id); // Remove from session tracking
            });

            setTimeout(() => notificationDiv.classList.add('show'), 50); // Animation
            
            // Auto-remove from DOM after a timeout
            setTimeout(() => {
                if (document.getElementById(domNotificationId)) {
                    document.getElementById(domNotificationId).remove();
                    displayedNotificationIds.delete(id); // Also remove from session tracking
                }
            }, 15000); // Auto-remove after 15 seconds (increased from 10)
        }

        async function fetchUnreadNotifications() {
            // console.log("Fetching unread notifications...");
            try {
                const response = await fetch('index.php?action=get_unread_notifications');
                if (!response.ok) {
                    console.error('Network response was not ok for fetching notifications. Status:', response.status);
                    return;
                }
                const data = await response.json();
                if (data.success && data.notifications) {
                    // console.log("Received notifications:", data.notifications);
                    if (data.notifications.length === 0) {
                        // console.log("No new unread notifications.");
                    }
                    data.notifications.forEach(notif => {
                        let title = ''; // Will be set in showNotificationJS if not provided
                        // Pass the DB notification type directly to showNotificationJS
                        showNotificationJS(notif.id, title, notif.message, notif.type, notif.formatted_created_at);
                    });
                } else if (data.message) {
                    console.error('API Error fetching notifications:', data.message);
                }
            } catch (error) {
                console.error('General Error fetching notifications:', error);
            }
        }

        async function markNotificationAsRead(notificationDbId) {
            // console.log(`Marking notification ${notificationDbId} as read...`);
            try {
                const response = await fetch(`index.php?action=mark_notification_read&id=${notificationDbId}`);
                const data = await response.json();
                if (!data.success) {
                     console.error(`Failed to mark notification ${notificationDbId} as read:`, data.message);
                }
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            fetchUnreadNotifications(); // Initial fetch on page load
            setInterval(fetchUnreadNotifications, notificationCheckInterval); // Periodic polling
        });

        // --- END: New Notification Logic ---
    </script>
</body>
</html>
<?php
// Close the database connection at the very end
if(isset($conn)) {
    $conn->close();
}
?>
