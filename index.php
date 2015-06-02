<?php

$data = fetchCurrentPage();
$pq = getCurrentTemplate();
/* process template parts, data['components'] tells us what components to allow */
loadMenus($pq,$data);
loadComponents($pq,$data);
compileDirectives($pq);
$pq = processPage($pq,$data);
$html = $pq->html();    

echo $html;

?>