<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ADMIN - flatcms">
    <meta name="author" content="cfconsultancy.nl">
	<meta name="robots" content="noindex">

    <title>ADMIN - flatcms</title>

    <!-- Bootstrap Core CSS -->
    <link href="./skins/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="./skins/css/sb-admin-2.css" rel="stylesheet">

	<!-- Responsive table CSS -->
	<link href="./skins/css/stacktable.css" rel="stylesheet">

    <!-- Checkboxes CSS -->
	<link href="./skins/css/switchery.min.css" rel="stylesheet">

	<link href="./skins/css/jquery.tagsinput.css" rel="stylesheet">

    <!-- Custom Fonts -->
    <link href="./skins/font-awesome-4.1.0/css/font-awesome.min.css" rel="stylesheet" type="text/css">

    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
        <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- Tinymce -->
	<script src="./tinymce/tinymce.min.js"></script>
    <!-- js editor -->
	<script src="./js-editor/ed.js"></script>


</head>

<body>

    <div id="wrapper">
	[controls]
	[menu]

        <div id="page-wrapper">

				[content]

		</div>
        <!-- /#page-wrapper -->

	</div>
    <!-- /#wrapper -->

	<div id="page-footer">
		&copy; FLATCMS <?php echo date("Y"); echo ' | Version ' . $this->version; ?>
	</div>
	<!-- /#footer -->

    <!-- jQuery Version 1.11.0 -->
    <script src="./skins/js/jquery-1.11.0.js"></script>

    <!-- jQuery Tags support -->
	<script src="./skins/js/jquery.tagsinput.js"></script>

    <!-- Bootstrap Core JavaScript -->
    <script src="./skins/js/bootstrap.min.js"></script>

    <!-- Responsive table -->
	<script src="./skins/js/stacktable.js"></script>

    <!-- Checkboxes Core JavaScript -->
    <script src="./skins/js/switchery.min.js"></script>

    <!-- Custom Theme JavaScript -->
	<script src="./js/jquery.main.js"></script>

    <!-- jQuery Tags settings -->
	<script>$(function() { $('#keywords').tagsInput({width:'auto',defaultText:'',defaultRemoveText:'<?php echo $this->getLanguage('delete'); ?>'});});</script>

</body>

</html>
