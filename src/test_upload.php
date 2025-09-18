<?php
if ($_SERVER['REQUEST_METHOD']==='POST') {
  var_dump($_FILES);
  exit;
}
?>
<!doctype html><meta charset="utf-8">
<h3>Test upload directo</h3>
<form method="post" enctype="multipart/form-data">
  <input type="file" name="img1">
  <button>Subir</button>
</form>