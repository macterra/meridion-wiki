<?php
ob_start();

include_once ("../bbs/Settings.php");
include_once ("../bbs/auth.php");

### Lets output all that info. ###
print <<<EOT
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<style type="text/css">
<!--
BODY          {font-family: Verdana, arial, helvetica, serif; font-size:12px;}
TABLE       {empty-cells: show }
TD            {font-family: Verdana, arial, helvetica, serif; color: #000000; font-size:12px;}
-->
</style>
<meta http-equiv="Content-Type" content="text/html; charset=ISO-8859-1">

<title>$txt[668]</title>
</head>

<body bgcolor="#FFFFFF" text="#000000">
EOT;

print "$authUser<br>\n";
print "$authWikiName<br>\n";
print "$authLevel<p>\n";

print ToWikiName("Dr. Sebby")."<br>\n";
print ToWikiName("tywick")."<br>\n";
print ToWikiName("BJKlien_com")."<br>\n";
print ToWikiName("WebWraith")."<br>\n";
print ToWikiName("na20ins@yahoo.com")."<br>\n";
print ToWikiName("NainsAtYahooCom")."<br>\n";
print ToWikiName("WEAP0NER")."<br>\n";
print ToWikiName("PANGosaurus")."<br>\n";
print ToWikiName("athe nonrex")."<br>\n";
print ToWikiName("Zloduska")."<br>\n";

for ($i = 0; $i < 30; $i++) {
  print "$i $settings[$i]<br>\n";
}

print <<<EOT
id_member = $ID_MEMBER;
reputation = $rep;
</body></html>
EOT;
?>
