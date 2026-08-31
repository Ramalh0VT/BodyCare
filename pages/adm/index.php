<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
	</head>	
	<body>
		<?php
			$usuarios = []
			$tabela_usuarios = ''
			foreach($usuarios as $usuario){
				$tabela_usuarios .= "<div> 
					<h2>.$usuario.</h2>
					<button></button>
					</div>"
			}
			echo $tabela_usuarios
		?>
	</body>
</html>
