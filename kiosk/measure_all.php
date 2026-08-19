<?php

header('Content-Type: text/plain');

echo "PHP User: " . trim(shell_exec('whoami')) . "\n";
echo "Hostname: " . gethostname() . "\n";
echo "PHP OS: " . php_uname() . "\n\n";

$output = [];
$status = 0;

exec("which python3 2>&1", $output, $status);

echo "which python3:\n";
print_r($output);

echo "\nStatus: $status\n";
?>
