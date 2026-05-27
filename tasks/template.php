<?php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="taskflow-template.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['title', 'description', 'due_date', 'priority', 'status', 'recurrence', 'categories', 'subtasks']);

fclose($out);
