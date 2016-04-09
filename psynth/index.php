<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<title>Photosynthesis</title>
	<style type="text/css">
		body {
			padding: 0;
			margin: 0;
			font-family: "Lucida Grande", Lucida, Verdana, sans-serif;
			font-size: 12px;
			line-height: 19px;
			background-color: #243a51;
			color: #333;
		}
		img {
			border: 0;
		}
		strong {
			background-color: #b12c1d;
			padding: 1px 2px;
			color: #eee;
			font-weight: normal;
		}
		h2 {
			font-size: 1.2em;
			font-weight: bold;
			margin: 0;
		}
		tt {
			font-size: 1.2em;
		}
		#container {
			width: 720px;
			margin: 0 auto 30px auto;
			background-color: #f5eee6;
		}
		#header-graphic {
			width: 720px;
			height: 250px;
			background: url('header1.jpg');
		}
		#header-text {
			height: 92px;
			border-top: 1px solid #770705;
		}
			#header-text div.left {
				line-height: 50%;
			}
			#header-text div.left img {
				position: absolute;
				margin: auto;
			}
			#header-text div.right {
				vertical-align: middle;
				font-weight: bold;
			}
		#confirmation {
			padding: 30px;
			background-color: #b12c1d;
			color: #fff;
			clear: both;
		}
		#pitch1 {
			background-color: #dccbaf;
			border-top: 1px solid #770705;
			clear: both;
		}
		#pitch2 {
			background-color: #dccbaf;
			border-top: 1px solid #770705;
			clear: both;
		}
		#screenshot {
			clear: both;
			width: 720px;
			height: 209px;
			background: url('screenshot.png');
			border-top: 1px solid #770705;
		}
		#register {
			clear: both;
			border-top: 1px solid #770705;
		}
		#footer {
			background-color: #dccbaf;
			padding: 15px 30px;
			clear: both;
			font-size: .85em;
			text-align: center;
			border-top: 1px solid #770705;
		}
		div.left {
			width: 270px;
			padding: 20px 60px 20px 30px;
			float: left;
			background-color: inherit;
		}
		div.right {
			width: 270px;
			padding: 20px 30px 20px 60px;
			float: right;
			background-color: inherit;
		}
	</style>
	<script type="text/javascript">
		function clearText (field) {
			if (field.defaultValue == field.value) field.value = '';
			else if (field.value == '') field.value = field.defaultValue;
		}
	</script>
</head>
<body>
	<div id="container">
		<div id="header-graphic">
			
		</div>
		<div id="header-text">
			<div class="left">
				<img src="logo.png" style="width: 289px; height: 92px;" alt="Richmond Sunlight" />
			</div>
			<div class="right">
				<p>Stop tracking legislation like it&rsquo;s 1993.
				Photosynthesis works the way you do, giving you a clear
				understanding of your legislation. At last.</p>
			</div>
		</div>
		
<?php
	if (!empty($_POST['email']))
	{
		
		mail('waldo@jaquith.org', 'Photosynthesis Beta Registrant', $_POST['email'].' '
			.$_SERVER['REMOTE_ADDR'],
			'From: Richmond Sunlight <do_not_reply@richmondsunlight.com>');
		
		?>
		<div id="confirmation">
			<h2>You&rsquo;re Registered!</h2>
			<p>Thank you for registering for the beta test. You will be contacted
			within a few days with your access information.</p>
		</div>
		<?php
	}
	
	else
	{
?>

		<div id="pitch1">
			<div class="left">
				<h2>The Tools You Need</h2>
				<p>E-mail notifications. RSS feeds. Smart portfolios. Web
				dashboard. Tagging. Community interface. <strong>Everything you
				need</strong> and everything you didn&rsquo;t know you need.</p>
			</div>
			<div class="right">
				<h2>The Way You Work</h2>
				<p>Route bill updates to different e-mail addresses, depending
				on topic and severity. Store bills in unlimited portfolios.
				Access from anywhere.</p>
			</div>
		</div>
		<div id="screenshot">
		</div>
		<div id="pitch2">
			<div class="left">
				<h2>Legislation Finds You</h2>
				<p>Provide the criteria for the sort of bills that you&rsquo;re
				interested in and they&rsquo;ll be queued for you as
				they&rsquo;re filed. <strong>It&rsquo;s that easy</strong>.
				No more hunting down voting records or sneaky bills.</p>
			</div>
			<div class="right">
				<h2>Crowdsource It</h2>
				<p>Create a shared portfolio of bills and your
				organization&rsquo;s position paper will be put in front of the
				grassroots on the bill&rsquo;s public page. <strong>Work <em>with</em>
				citizen activists</strong>.</p>
			</div>
		</div>
		<div id="register">
			<div class="left">
				The first beta invitations will go out November 19. The system
				opens to the public December 15. Sign up now for the beta
				test&mdash;only <tt>49</tt> slots are left.
			</div>
			<div class="right">
				<h2>Register for the Beta Test</h2>
				<form method="post" enctype="multipart/form-data" action="/photosynthesis/">
					<input type="text" name="email" size="30" maxlength="30"
						value="Enter Your E-Mail Address" onFocus="clearText(this);" onBlur="clearText(this)"/>
					<input type="submit" name="submit" value="Register" />
				</form>
			</div>
		</div>
<?php
	}
?>
		<div id="footer">
			<p>A program of the <a
			href="http://www.virginiainterfaithcenter.org/">Virginia Interfaith
			Center</a>. Created by <a href="http://waldo.jaquith.org/">Waldo
			Jaquith</a>.</p>
		</div>
	</div>
</body>
</html>