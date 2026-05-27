<?php
function get_task_subtasks($conn, $task_id) {
    $stmt = $conn->prepare("SELECT * FROM subtasks WHERE task_id = ? ORDER BY position ASC, created_at ASC");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $subtasks = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $subtasks;
}

function get_tasks_subtasks_map($conn, $task_ids) {
    if (empty($task_ids)) return [];
    $placeholders = implode(',', array_fill(0, count($task_ids), '?'));
    $types = str_repeat('i', count($task_ids));
    $stmt = $conn->prepare(
        "SELECT * FROM subtasks WHERE task_id IN ($placeholders) ORDER BY position ASC, created_at ASC"
    );
    $stmt->bind_param($types, ...$task_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        $map[$row['task_id']][] = $row;
    }
    $stmt->close();
    return $map;
}
