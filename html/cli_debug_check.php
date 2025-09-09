<?php
// Put a breakpoint on the line below.
$pid = getmypid();
fwrite(STDOUT, "PID: {$pid}\n");
// sleep(5);
echo "done\n";
