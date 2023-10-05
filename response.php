<?php if ($_POST['name'] == 'Bob'): ?>
    <a hx-post='response.php' target='closest td' hx-vals='{"name": "Alice"}' hx-swap='outerHTML'>Alice</a>
<?php elseif ($_POST['name'] == 'Alice'): ?>
    <a hx-post='response.php' target='closest td' hx-vals='{"name": "Bob"}' hx-swap='outerHTML'>Bob</a>
<?php endif; ?>
