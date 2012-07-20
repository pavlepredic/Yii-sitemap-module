<h3>Sitemap</h3>
<br>
<ul>
<?php foreach ($urls as $url => $data) : ?>
<li><?php echo CHtml::link($url, $url) ?></li>
<?php endforeach; ?>
</ul>