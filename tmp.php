<?php
include('bootstrap.php');

$temp = new \phpman\Template();
$temp->output(__FILE__,'index');
exit;
?>

<rt:template name="top">
	TOP
	<rt:block name="abc">
		ABC
	</rt:block>
</rt:template>


<rt:template name="index" href="#top">
	<rt:block name="abc">
		aaaa
	</rt:block>
</rt:template>


