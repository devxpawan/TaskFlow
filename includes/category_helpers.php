<?php
function get_user_categories($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $categories;
}

function get_task_categories($conn, $task_id) {
    $stmt = $conn->prepare(
        "SELECT c.* FROM categories c
         JOIN task_categories tc ON c.id = tc.category_id
         WHERE tc.task_id = ?"
    );
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $categories;
}

function get_tasks_categories_map($conn, $task_ids) {
    if (empty($task_ids)) return [];
    $placeholders = implode(',', array_fill(0, count($task_ids), '?'));
    $types = str_repeat('i', count($task_ids));
    $stmt = $conn->prepare(
        "SELECT tc.task_id, c.id, c.name, c.color
         FROM task_categories tc
         JOIN categories c ON c.id = tc.category_id
         WHERE tc.task_id IN ($placeholders)
         ORDER BY c.name"
    );
    $stmt->bind_param($types, ...$task_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    while ($row = $result->fetch_assoc()) {
        $map[$row['task_id']][] = $row;
    }
    $stmt->close();
    return $map;
}
