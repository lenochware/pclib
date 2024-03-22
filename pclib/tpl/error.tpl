<?elements
string code
string message
string severity
string file
string line
string exceptionClass
string route
string timestamp
string trace
?>
<!DOCTYPE html>
<html>
	<head>
		<title>An error occured.</title>
	</head>
	<body>
		<h1>We're Sorry!</h1>
		<p>Oops! Something went wrong, and we couldn't process your request.</p>
		<p>Please try again later or contact our <a href="mailto:support@example.com">support team</a> for assistance.</p>
		<p><a href="/">Return to the Home Page</a></p>
		<p style="color:gray">{exceptionClass} in <b>{route}</b>, timestamp: {timestamp}.</p>
	</body>
</html>