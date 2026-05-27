<?php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="taskflow-template.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['title', 'description', 'due_date', 'priority', 'status', 'recurrence', 'categories', 'subtasks']);

fputcsv($out, [
    'Grocery shopping',
    'Buy milk, eggs, and bread',
    '2026-06-15',
    'Medium',
    'Pending',
    'weekly',
    'Personal, Errands',
    '[ ] Pick up milk|[ ] Get eggs|[ ] Buy bread'
]);

fputcsv($out, [
    'Write report',
    'Q2 financial summary',
    '2026-06-20',
    'High',
    'Pending',
    'none',
    'Work',
    '[x] Gather data|[ ] Draft outline|[ ] Review with team'
]);

fputcsv($out, [
    'Minimal task',
    '',
    '',
    'Medium',
    'Pending',
    'none',
    '',
    ''
]);

fclose($out);
