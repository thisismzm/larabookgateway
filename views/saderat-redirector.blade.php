<html>
<head>
	<title>در حال انتقال به سرور بانک</title>
	<style type="text/css">
		#back {
			position: fixed;
			left: 0;
			top: 0;
			width: 100%;
			height: 100%;
			overflow: visible;
			background: #fff;
		}
		.center{
			direction: rtl;
			font-family: tahoma;
			color: #333;
			position: absolute;
			top: 50%;
			left: 50%;
			transform: translate3d(-50%,-50%,0);
			width: 300px;
			height: 200px;
			text-align: center;
		}
		#loader {
			border: 12px solid #f3f3f3; /* Light grey */
			border-top: 12px solid #3498db; /* Blue */
			border-radius: 50%;
			width: 50px;
			height: 50px;
			animation: spin 2s linear infinite;
			margin: 10px auto;
		}

		@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
		}
	</style>
</head>
	<body>
		<div class="center">
			<div id="loader"></div>
			<p>در حال اتصال به سرور بانک ...</p>
		</div>
		<script>
			var form = document.createElement("form");
			form.setAttribute("method", "POST");
			form.setAttribute("action", "https://mabna.shaparak.ir");
			form.setAttribute("target", "_self");

			var hiddenField = document.createElement("input");
			hiddenField.setAttribute("name", "TOKEN");
			hiddenField.setAttribute("value", "{{$refId}}");
			form.appendChild(hiddenField);

			document.body.appendChild(form);
			form.submit();
			document.body.removeChild(form);
		</script>
	</body>
</html>
