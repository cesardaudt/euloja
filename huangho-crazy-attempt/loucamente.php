<?php
# This is getting more and more horrible. Will refactor Real Soon Now.

error_reporting(E_ALL | E_STRICT);
session_start();

# Magic quoting sucks.
function unquote($txt) {
	if (get_magic_quotes_gpc())
		return stripslashes($txt);
	else
		return $txt;
}

class User {
	public $email;     # String; primary key
	public $cpf;       # String (não cabe num int, nem inventem)
	public $name;      # String
	public $address;   # String
	public $phone;     # String
	public $password;  # String

	public $bankName;
	public $bankAgency;
	public $bankAccountNumber;

	# TODO: Write a decent constructor. Ideally callers should not rely on
	# argument order.
}

class Product {
	public $id;       # Int; primary key; autogenerated
	public $name;
	public $author;
	public $year;
	public $edition;
	public $conservationState;
	public $warranty;
	public $deliveryModes;
	public $price;
	public $notes;
}

class DataBase {
	public $pdo;


	function createTable($name, $attributes) {
		$cmd = "CREATE TABLE $name (" . implode(",", $attributes) . ")";
		$this->pdo->exec($cmd);
	}
}


class UserBase extends DataBase {
	// FIXME: UserBase and ProductBase are really one single database.

	function __construct() {
		$this->pdo = new PDO('sqlite:users.sqlite');
		$this->createTable('Users', array_keys(get_class_vars('User')));
		print_r($this->pdo->errorInfo());
	}

	function findUserByEmail($email) {
		$query = $this->pdo->prepare('SELECT * from Users where email = :email');
		$query->execute(Array(':email' => $email));
		return $query->fetchObject('User');
	}

	function findUserByCPF($cpf) {
		$query = $this->pdo->prepare('SELECT * from Users where cpf = :cpf');
		$query->execute(Array(':cpf' => $cpf));
		return $query->fetchObject('User');
	}

	function addUser($user) {
		$colon_keys = Array();
		$keys = Array();
		$values = Array();
		foreach (get_object_vars($user) as $key => $value) {
			array_push($keys, $key);
			array_push($colon_keys, ":$key");
			$values[":$key"] = $value;
		}

		# TODO: Esse cara deveria verificar se user já existe, não o addUserSubmit.

		$query = $this->pdo->prepare('INSERT INTO Users (' . implode(",", $keys) . ') VALUES (' . implode(",", $colon_keys) . ')');
		$query->execute($values);
		print_r($this->pdo->errorInfo());
		return TRUE;
	}

	function dumpAllUsers() {
		# DEBUG.

		return $this->pdo->query('SELECT * FROM Users')->fetchAll();
	}
}

class ProductBase extends DataBase{
	// µµµ.
}

class Session {
	public $userEmail;
}

class MainController {
	public $userBase;
	public $productBase;
	public $session;

	function __construct() {
		$this->userBase = new UserBase();
		$this->productBase = new ProductBase();
	}

	function init() {
		if (!isset($_SESSION['session']))
			$_SESSION['session'] = new Session();
		$this->session = $_SESSION['session'];

		if (isset($_REQUEST['loginAction']))
			$loginAction = $_REQUEST['loginAction'];
		else
			$loginAction = 'none';

		if (isset($_REQUEST['action']))
			$action = $_REQUEST['action'];
		else
			$action = 'home';

		switch ($loginAction) {
			case 'login':
				$login = new LoginController($this->userBase, $action);
				$user = $login->authenticate();
				if ($user)
					$this->session->userEmail = $user->email;
				else
					return;  ## Bad!!
				break;

			case 'logout':
				$this->session->userEmail = NULL;
				break;
		}

		switch ($action) {
			#case 'validateLogin':
			#	$this->validateLogin();
			#	break;

			# These will be merged and moved out to a controller.
			case 'addUserForm':
				$this->addUserForm();
				break;
			case 'addUserSubmit':
				$this->addUserSubmit();
				break;

			# This is really not an action, but rather a 'pre-action', i.e.,
			# something you execute prior to an actual action. Shall be changed.
			#case 'loginForm':

			case 'dumpAllUsers':
				### DEBUG!!
				echo "<PRE>";
				print_r($this->userBase->dumpAllUsers());
				echo "</PRE>";
				break;

			case 'home':
				$this->home();
				break;
			default:
				echo "WTF is $action?\n";
		}

	}

	function home() {
		if ($this->session->userEmail)
			echo "Logged in as " . $this->session->userEmail . "\n";
		else
			echo "Not logged in.\n";

		?>
			<UL>
				<LI><A HREF="loucamente.php?action=addUserForm">Adicionar usuário</A>
				<LI><A HREF="loucamente.php?action=dumpAllUsers">Dump all users (DEBUG)</A>
				<LI><A HREF="loucamente.php?action=home&loginAction=login">Login</A>
				<LI><A HREF="loucamente.php?action=home&loginAction=logout">Logout</A>
			</UL>
		<?php
	}

	#function validateLogin() {
	#	# TODO: There should probably be an AuthController of sorts.
	#	# -> Now there is.
	#	$email = $_REQUEST['email'];
	#	$password = $_REQUEST['password'];
	#
	#	$user = $this->userBase->findUserByEmail($email);
	#	if ($user) {
	#		if ($user->password == $password) {
	#			# Ai, encryption, µµµ.
	#			$this->session->userEmail = $user->email;
	#			echo "Thou loggedst! <A HREF='loucamente.php'>Home</A>\n";
	#			return TRUE;
	#		}
	#		else {
	#			echo "Wrong pass\n";
	#		}
	#	}
	#	else {
	#		echo "Non-existent user\n";
	#	}
	#
	#	return FALSE;
	#}

	function addUserForm() {
		# This should also have a controller. This should really be a plain old procedure.

		?>
		<FORM ACTION="loucamente.php?action=addUserSubmit" METHOD="post">
			<TABLE>
				<?php
					foreach (Array('name'=>'Nome', 'email'=>'E-mail', 'cpf'=>'CPF', 'address'=>'Endereço', 'phone'=>'Telefone', 'password'=>'Senha', 'confirm_password'=>'Mais senha') as $key => $label)
						echo "<TR><TD>$label<TD><INPUT TYPE='text' NAME='$key'>\n"
				?>

			</TABLE>
			<INPUT TYPE="submit" VALUE="Manda bala">
		</FORM>
		<?php
	}

	function addUserSubmit() {
		# TODO: Validate all things.

		# Does the user already exist?
		if ($this->userBase->findUserByEmail($_REQUEST['email'])) {
			echo "User already exists by that e-mail.\n";
			return FALSE;
		}

		if ($this->userBase->findUserByCPF($_REQUEST['cpf'])) {
			echo "User already exists by that CPF.\n";
			return FALSE;
		}

		# TODO: Have a decent constructor.
		$user = new User();
		$user->name = $_REQUEST['name'];
		$user->email = $_REQUEST['email'];
		$user->cpf = $_REQUEST['cpf'];
		$user->address = $_REQUEST['address'];
		$user->phone = $_REQUEST['phone'];
		$user->password = $_REQUEST['password'];


		if ($this->userBase->addUser($user)) {
			echo "User added.\n";
			return TRUE;
		}
		else {
			echo "User addition failed!\n";
			return FALSE;
		}
	
	
	}

}





///--- This is how the world should be (almost). ----------

class Attribute {
	public $key;
	public $label;
	public $defaultValue;
	public $value;
	public $isMandatory;

	function __construct($key, $label, $type='text', $isMandatory=FALSE, $defaultValue=NULL) {
		$this->key = $key;
		$this->label = $label;
		$this->type = $type;
		$this->defaultValue = $defaultValue;
		$this->isMandatory = $isMandatory;

		# Questionable.
		if (isset($_REQUEST[$key]))
			$this->value = unquote($_REQUEST[$key]);
		else
			$this->value = $this->defaultValue;
	}

	function printHTML() {
		$value = htmlspecialchars($this->value, ENT_QUOTES);
		echo "<TR><TD>{$this->label}<TD><INPUT TYPE='{$this->type}' NAME='{$this->key}' VALUE='$value'>\n";
	}
}

class UIForm {
	public $attributes = Array();
	public $action;

	function addAttribute($attr) {
		// Assuming PHP will keep array order.
		$this->attributes[$attr->key] = $attr;
		
		// Cannot use call_user_func_array with constructor. (Why not? You can't, that's why.)
	}

	function getAttributeValue($key) {
		return $this->attributes[$key]->value;
	}

	function printHTML() {
		echo "<FORM ACTION='{$this->action}' METHOD='POST'>\n";
		echo "<TABLE>\n";
		foreach ($this->attributes as $attr) {
			$attr->printHTML();
		}
		echo "</TABLE>\n";
		echo "<INPUT TYPE='Submit' VALUE='Manda bala'>\n";
		echo "</FORM>\n";
	}
}

class LoginForm extends UIForm {
	public $action;
	public $errorStatus = NULL;

	function __construct($errorStatus=NULL) {
		$this->action = $_SERVER['REQUEST_URI'];  // Half-bad.
		$this->addAttribute(new Attribute("email", "E-mail"));
		$this->addAttribute(new Attribute("password", "Password", "password"));
	}

	function printHTML() {
		if ($this->errorStatus) {
			echo "<P>Erro de autenticação: {$this->errorStatus}";
		}
		parent::printHTML();
	}
}

class LoginController {
	public $ui;
	public $userBase;

	function __construct($userBase) {
		$this->ui = new LoginForm();
		$this->userBase = $userBase;
	}

	function authenticate() {
		if ($email = $this->ui->getAttributeValue('email')) {
			// We are mid-login.
			$user = $this->userBase->findUserByEmail($email);
			if ($user) {
				$formPassword = $this->ui->getAttributeValue('password');
				if ($user->password == $formPassword) {
					return $user;
				}
				else {
					$this->ui->errorStatus = 'WRONG_PASSWORD';
				}
			}
			else {
				$this->ui->errorStatus = 'UNKNOWN_USER';
			}
		}

		$this->ui->printHTML();
	}

}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
	<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=UTF-8">
	<TITLE>Euloja</TITLE>
</HEAD>
<BODY>
<?php

$main = new MainController();
$main->init();

?>
</BODY>
</HTML>
