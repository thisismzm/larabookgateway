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
			var form = document.createElement("FORM");
			form.setAttribute("method", "POST");
			form.setAttribute("action", "{{$url}}");
			form.setAttribute("target", "_self");

			var invoiceNumber = document.createElement("input");
			invoiceNumber.setAttribute("name", "invoiceNumber");
			invoiceNumber.setAttribute("value", "{{$invoiceNumber}}");

			form.appendChild(invoiceNumber);

			var invoiceDate = document.createElement("input");
			invoiceDate.setAttribute("name", "invoiceDate");
			invoiceDate.setAttribute("value", "{{$invoiceDate}}");
			form.appendChild(invoiceDate);

			var amount = document.createElement("input");
			amount.setAttribute("name", "amount");
			amount.setAttribute("value", "{{$amount}}");
			form.appendChild(amount);

			var terminalCode = document.createElement("input");
			terminalCode.setAttribute("name", "terminalCode");
			terminalCode.setAttribute("value", "{{$terminalCode}}");
			form.appendChild(terminalCode);

			var merchantCode = document.createElement("input");
			merchantCode.setAttribute("name", "merchantCode");
			merchantCode.setAttribute("value", "{{$merchantCode}}");
			form.appendChild(merchantCode);

			var timeStamp = document.createElement("input");
			timeStamp.setAttribute("name", "timeStamp");
			timeStamp.setAttribute("value", "{{$timeStamp}}");
			form.appendChild(timeStamp);

			var action = document.createElement("input");
			action.setAttribute("name", "action");
			action.setAttribute("value", "{{$action}}");
			form.appendChild(action);

			var sign = document.createElement("input");
			sign.setAttribute("name", "sign");
			sign.setAttribute("value", "{{$sign}}");
			form.appendChild(sign);

			var redirectAddress = document.createElement("input");
			redirectAddress.setAttribute("name", "redirectAddress");
			redirectAddress.setAttribute("value", "{{$redirectUrl}}");
			form.appendChild(redirectAddress);

			document.body.appendChild(form);
			form.submit();
			//document.write(form.outerHTML());
			document.body.removeChild(form);
		</script>
	</body>
</html>
