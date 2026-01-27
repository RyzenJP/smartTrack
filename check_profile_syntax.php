<?php
// Check if profile.php has syntax errors
$file = __DIR__ . '/profile.php';

echo "Checking profile.php for syntax errors...<br><br>";

// Check syntax
$output = [];
$return_var = 0;
exec("php -l \"$file\" 2>&1", $output, $return_var);

if ($return_var === 0) {
    echo "<span style='color: green;'>✅ NO SYNTAX ERRORS FOUND!</span><br>";
    echo "profile.php syntax is valid.<br>";
} else {
    echo "<span style='color: red;'>❌ SYNTAX ERRORS FOUND:</span><br>";
    foreach ($output as $line) {
        echo htmlspecialchars($line) . "<br>";
    }
}

echo "<br><br>";

// Also check if file exists and is readable
if (file_exists($file)) {
    echo "✅ File exists<br>";
    echo "File size: " . filesize($file) . " bytes<br>";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($file)) . "<br>";
} else {
    echo "❌ File does not exist!<br>";
}
?>



