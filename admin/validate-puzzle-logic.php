<?php
/**
 * Puzzle Logic Validator
 * Helps validate that puzzles have clear, logical contradictions
 */

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Puzzle.php';

$puzzleId = $_GET['id'] ?? null;

if (!$puzzleId) {
    die("Please provide puzzle ID: ?id=1");
}

$puzzle = new Puzzle();
$puzzleData = $puzzle->getPuzzleById($puzzleId);

if (!$puzzleData) {
    die("Puzzle not found");
}

$statements = $puzzle->getStatements($puzzleId);
$solution = $puzzle->getSolution($puzzleId);

// Find correct statement
$correctStatement = null;
foreach ($statements as $stmt) {
    if ($stmt['is_correct_answer']) {
        $correctStatement = $stmt;
        break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Puzzle Logic Validator - Puzzle #<?php echo $puzzleId; ?></title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; }
        .section { margin: 30px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; }
        .warning { background: #fff3cd; border-color: #ffc107; }
        .error { background: #f8d7da; border-color: #dc3545; }
        .success { background: #d4edda; border-color: #28a745; }
        .statement { padding: 15px; margin: 10px 0; border-left: 4px solid #ddd; }
        .statement.correct { border-left-color: #28a745; background: #d4edda; }
        .statement.incorrect { border-left-color: #dc3545; background: #f8d7da; }
        h2 { color: #333; }
        h3 { color: #666; margin-top: 20px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .check-item { margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Puzzle Logic Validator - Puzzle #<?php echo $puzzleId; ?></h1>
    
    <div class="section">
        <h2>Puzzle: <?php echo htmlspecialchars($puzzleData['title']); ?></h2>
        <p><strong>Theme:</strong> <?php echo htmlspecialchars($puzzleData['theme'] ?? 'N/A'); ?></p>
        <p><strong>Difficulty:</strong> <?php echo ucfirst($puzzleData['difficulty']); ?></p>
    </div>
    
    <div class="section">
        <h2>Case Summary</h2>
        <p><?php echo nl2br(htmlspecialchars($puzzleData['case_summary'])); ?></p>
    </div>
    
    <div class="section">
        <h2>Incident Report</h2>
        <pre style="white-space: pre-wrap;"><?php echo htmlspecialchars($puzzleData['report_text']); ?></pre>
    </div>
    
    <div class="section">
        <h2>Statements</h2>
        <?php foreach ($statements as $index => $stmt): ?>
            <div class="statement <?php echo $stmt['is_correct_answer'] ? 'correct' : 'incorrect'; ?>">
                <strong><?php echo $index + 1; ?>. 
                    <?php if ($stmt['is_correct_answer']): ?>
                        ✓ CORRECT (This should contradict the facts)
                    <?php else: ?>
                        Incorrect (This should be consistent)
                    <?php endif; ?>
                </strong><br>
                <?php echo htmlspecialchars($stmt['statement_text']); ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($solution): ?>
    <div class="section">
        <h2>Solution</h2>
        <h3>Explanation</h3>
        <p><?php echo nl2br(htmlspecialchars($solution['explanation'])); ?></p>
        
        <h3>Detailed Reasoning</h3>
        <p><?php echo nl2br(htmlspecialchars($solution['detailed_reasoning'] ?? 'N/A')); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="section">
        <h2>Validation Checks</h2>
        
        <?php
        $checks = [];
        
        // Check 1: Correct statement exists
        if ($correctStatement) {
            $checks[] = ['status' => 'success', 'message' => 'Correct statement identified'];
        } else {
            $checks[] = ['status' => 'error', 'message' => 'ERROR: No correct statement found'];
        }
        
        // Check 2: Only one correct statement
        $correctCount = 0;
        foreach ($statements as $stmt) {
            if ($stmt['is_correct_answer']) {
                $correctCount++;
            }
        }
        if ($correctCount === 1) {
            $checks[] = ['status' => 'success', 'message' => 'Exactly one correct statement'];
        } else {
            $checks[] = ['status' => 'error', 'message' => "ERROR: {$correctCount} correct statements found (should be 1)"];
        }
        
        // Check 3: Solution explains the contradiction
        if ($solution && !empty($solution['explanation'])) {
            $explanationLower = strtolower($solution['explanation']);
            $hasContradictionWords = (
                strpos($explanationLower, 'contradict') !== false ||
                strpos($explanationLower, "doesn't fit") !== false ||
                strpos($explanationLower, 'does not fit') !== false ||
                strpos($explanationLower, 'inconsistent') !== false ||
                strpos($explanationLower, 'wrong') !== false ||
                strpos($explanationLower, 'incorrect') !== false
            );
            
            if ($hasContradictionWords) {
                $checks[] = ['status' => 'success', 'message' => 'Solution mentions contradiction/inconsistency'];
            } else {
                $checks[] = ['status' => 'warning', 'message' => 'WARNING: Solution may not clearly explain the contradiction'];
            }
        } else {
            $checks[] = ['status' => 'error', 'message' => 'ERROR: No solution explanation found'];
        }
        
        // Check 4: Solution references specific facts
        if ($solution && $correctStatement) {
            $summaryReportText = strtolower($puzzleData['case_summary'] . ' ' . $puzzleData['report_text']);
            $statementText = strtolower($correctStatement['statement_text']);
            $solutionText = strtolower($solution['explanation'] . ' ' . ($solution['detailed_reasoning'] ?? ''));
            
            // Extract potential facts (times, numbers, names, locations)
            preg_match_all('/\d{1,2}:\d{2}/', $summaryReportText, $timesInReport);
            preg_match_all('/\d{1,2}:\d{2}/', $statementText, $timesInStatement);
            
            // Check for time contradictions
            if (!empty($timesInReport[0]) && !empty($timesInStatement[0])) {
                $timesMatch = false;
                foreach ($timesInReport[0] as $reportTime) {
                    foreach ($timesInStatement[0] as $stmtTime) {
                        if ($reportTime === $stmtTime) {
                            $timesMatch = true;
                            break 2;
                        }
                    }
                }
                if (!$timesMatch && count($timesInReport[0]) > 0) {
                    $checks[] = ['status' => 'success', 'message' => 'POTENTIAL TIME CONTRADICTION: Statement time differs from report times'];
                }
            }
            
            // Check if solution mentions specific facts from report
            $mentionsSpecific = (
                preg_match('/\d{1,2}:\d{2}/', $solutionText) ||
                preg_match('/\d+/', $solutionText) ||
                strpos($solutionText, 'report') !== false ||
                strpos($solutionText, 'summary') !== false
            );
            
            if ($mentionsSpecific) {
                $checks[] = ['status' => 'success', 'message' => 'Solution references specific facts or report'];
            } else {
                $checks[] = ['status' => 'warning', 'message' => 'WARNING: Solution may not reference specific facts from report'];
            }
        }
        
        // Display checks
        foreach ($checks as $check):
            $class = $check['status'];
        ?>
            <div class="check-item <?php echo $class; ?>">
                <strong><?php echo strtoupper($check['status']); ?>:</strong> <?php echo htmlspecialchars($check['message']); ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="section">
        <h2>Manual Review Checklist</h2>
        <p>Review the following manually:</p>
        <ul>
            <li>Does the correct statement contradict a <strong>specific fact</strong> stated in the summary or report?</li>
            <li>Can you point to the exact sentence in the summary/report that contradicts the statement?</li>
            <li>Would a reasonable player be able to identify this contradiction by comparing the statement to the summary/report?</li>
            <li>Do all other statements align with the facts in the summary/report?</li>
            <li>Is the solution explanation clear and specific about what is being contradicted?</li>
        </ul>
    </div>
    
    <p><a href="puzzle-edit.php?id=<?php echo $puzzleId; ?>">Edit Puzzle</a> | <a href="index.php">Back to Admin</a></p>
</body>
</html>

