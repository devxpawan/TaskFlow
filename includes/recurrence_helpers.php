<?php
function next_recurring_date($current_date, $interval) {
    if (empty($current_date) || $interval === 'none') return null;

    try {
        $dt = new DateTime($current_date);
    } catch (Exception $e) {
        return null;
    }

    switch ($interval) {
        case 'daily':
            $dt->modify('+1 day');
            break;
        case 'weekly':
            $dt->modify('+7 days');
            break;
        case 'monthly':
            $dt->modify('+1 month');
            break;
        case 'yearly':
            $dt->modify('+1 year');
            break;
        default:
            return null;
    }

    return $dt->format('Y-m-d');
}

function duplicate_recurring_task($conn, $task, $user_id) {
    $due_date = next_recurring_date($task['due_date'], $task['recurrence']);
    if ($due_date === null) return null;

    $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, status, recurrence, position)
                            VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?)");
    $stmt->bind_param("isssssi", $user_id, $task['title'], $task['description'], $due_date, $task['priority'], $task['recurrence'], $task['position']);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();

    // Copy categories
    $cat_stmt = $conn->prepare("SELECT category_id FROM task_categories WHERE task_id = ?");
    $cat_stmt->bind_param("i", $task['id']);
    $cat_stmt->execute();
    $cat_ids = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cat_stmt->close();

    if (!empty($cat_ids)) {
        $ins_tc = $conn->prepare("INSERT INTO task_categories (task_id, category_id) VALUES (?, ?)");
        foreach ($cat_ids as $c) {
            $ins_tc->bind_param("ii", $new_id, $c['category_id']);
            $ins_tc->execute();
        }
        $ins_tc->close();
    }

    // Copy subtasks (uncompleted)
    $sub_stmt = $conn->prepare("SELECT title FROM subtasks WHERE task_id = ?");
    $sub_stmt->bind_param("i", $task['id']);
    $sub_stmt->execute();
    $subs = $sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sub_stmt->close();

    if (!empty($subs)) {
        $ins_sub = $conn->prepare("INSERT INTO subtasks (task_id, title) VALUES (?, ?)");
        foreach ($subs as $s) {
            $ins_sub->bind_param("is", $new_id, $s['title']);
            $ins_sub->execute();
        }
        $ins_sub->close();
    }

    return $new_id;
}
