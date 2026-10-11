<?php
require dirname(__DIR__).'/app/bootstrap.php';
$cases=['/auth.php'=>'/','/public/auth.php'=>'/public/','/gather/public/auth.php'=>'/gather/public/','/nested/my-library/public/index.php'=>'/nested/my-library/public/'];
foreach($cases as $script=>$expected){if(session_cookie_path($script)!==$expected)throw new RuntimeException('Wrong cookie path for '.$script);}
if(session_cookie_name('/gather/public/')===session_cookie_name('/another/public/'))throw new RuntimeException('Cookie name collision');
echo "PASS: root, public, renamed and nested cookie paths; separate installation cookie names.\n";
