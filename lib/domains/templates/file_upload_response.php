<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <script type="text/javascript">
    <!--
    function delayer(){
        window.location = "<?php echo($return_uri); ?>";
    }
    //-->
    </script>
  </head>
  <body onLoad="setTimeout('delayer()', 5000)">
      <h1>We have recieved your file</h1>
      <p>So we are sending you back to the complettion URI you application supplied us</p>
      <a href="<?php echo($return_uri); ?>"><?php echo($return_uri); ?></a>
      <p>Note, if your browser does not redirect in 5 sec, you can simply click the link above</p>
  </body>
</html>
