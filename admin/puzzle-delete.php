<?php
require_once 'auth.php';
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Puzzle.php';

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$puzzleId = (int)$_GET['id'];
$puzzle = new Puzzle();

// Get puzzle info for confirmation
$puzzleData = $puzzle->getPuzzleById($puzzleId);

if (!$puzzleData) {
    header('Location: index.php?error=not_found');
    exit;
}

// Delete the puzzle (cascading deletes will handle related records)
$puzzle->deletePuzzle($puzzleId);

header('Location: index.php?message=deleted');
exit;
