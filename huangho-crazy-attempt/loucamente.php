<?php

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

		// Check for a pending login action.
		switch ($loginAction) {
			case 'login':
				# TODO: is loginAction is 'login' and user is already logged in,
				# it might be better to just ignore the preaction, so that actions
				# that require logging in can safely set loginAction to 'login',
				# whether or not the user is already logged in. Yea?

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

		// Then perform the main action.
		switch ($action) {
			#case 'validateLogin':
			#	$this->validateLogin();
			#	break;

			# These will be merged and moved out to a controller.
			case 'addUser':
				$addUser = new AddUserController($this->userBase);
				$addUser->act();
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
			echo "<P>Logged in as " . $this->session->userEmail . "\n";
		else
			echo "<P>Not logged in.\n";

		?>
			<UL>
				<LI><A HREF="loucamente.php?action=addUser">Adicionar usuário</A>
				<LI><A HREF="loucamente.php?action=dumpAllUsers">Dump all users (DEBUG)</A>
				<LI><A HREF="loucamente.php?action=home&loginAction=login">Login</A>
				<LI><A HREF="loucamente.php?action=home&loginAction=logout">Logout</A>
			</UL>
		<?php
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
	public $errors = Array();

	function __construct() {
		$this->action = $_SERVER['REQUEST_URI'];  // Half-bad.
	}

	function addAttribute($attr) {
		// Assuming PHP will keep array order.
		$this->attributes[$attr->key] = $attr;
		
		// Cannot use call_user_func_array with constructor. (Why not? You can't, that's why.)
	}

	function getAttributeValue($key) {
		return $this->attributes[$key]->value;
	}

	function addError($error) {
		array_push($this->errors, $error);
	}

	function printHTML() {
		echo "<FORM ACTION='{$this->action}' METHOD='POST'>\n";
		echo "<TABLE>\n";

		foreach ($this->errors as $error) {
			echo "<P>ERRO: $error";
		}

		foreach ($this->attributes as $attr) {
			$attr->printHTML();
		}
		echo "</TABLE>\n";
		echo "<INPUT TYPE='Submit' VALUE='Manda bala'>\n";
		echo "</FORM>\n";
	}

	function checkMandatory() {
		$allOK = TRUE;
		foreach ($this->attributes as $attr) {
			if ($attr->isMandatory && !$this->getAttributeValue($attr->key)) {
				# TODO: Perhaps separate check logic from messages?
				$this->addError("Campo '{$attr->label}' é obrigatório!");
				$allOK = FALSE;
			}
		}
		return $allOK;
	}
}

class LoginForm extends UIForm {
	public $errorStatus = NULL;

	function __construct($errorStatus=NULL) {
		parent::__construct();
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

class AddUserForm extends UIForm {
	public $errorStatus;

	function __construct() {
		parent::__construct();
		$this->action .= "&mid_action=1"; // This is just horrible.
		$this->addAttribute(new Attribute('name', "Nome completo", 'text', TRUE));
		$this->addAttribute(new Attribute('cpf', "CPF", 'text', TRUE));
		$this->addAttribute(new Attribute('email', "E-mail", 'text', TRUE));
		$this->addAttribute(new Attribute('address', "Endereço", 'text', TRUE));
		$this->addAttribute(new Attribute('phone', "Telefone", 'text', TRUE));
		$this->addAttribute(new Attribute('password', "Senha", 'password', TRUE));
		$this->addAttribute(new Attribute('password_confirmation', "Confirme a senha", 'password', TRUE));
	}

	function printHTML() {
		if ($this->errorStatus) {
			echo "<P>Error adding user: {$this->errorStatus}\n";
		}
		parent::printHTML();
	}
}

class AddUserController {
	public $ui;
	public $userBase;

	function __construct($userBase) {
		$this->ui = new AddUserForm();
		$this->userBase = $userBase;
	}

	function act() {
		if (isset($_REQUEST['mid_action'])) {
			if ($this->validate()) {
				# TODO: Have a decent constructor.
				$user = new User();
				$user->name = $this->ui->getAttributeValue('name');
				$user->email = $this->ui->getAttributeValue('email');
				$user->cpf = $this->ui->getAttributeValue('cpf');
				$user->address = $this->ui->getAttributeValue('address');
				$user->phone = $this->ui->getAttributeValue('phone');
				$user->password = $this->ui->getAttributeValue('password');
				if ($this->userBase->addUser($user)) {
					echo "<P>Yay! <A HREF='loucamente.php?action=home'>Home</A>";
					return TRUE;
				}
				else {
					echo "Fail!\n";
				}
			}
		}
		$this->ui->printHTML();
	}

	function validate() {
		$this->ui->checkMandatory();

		# We check this here, rather than in addUser, because we want to inform
		# the user as soon as possible that his/her CPF/e-mail is already registered.
		$email=""; $cpf="";
		if (($cpf = $this->ui->getAttributeValue('cpf')) && $this->userBase->findUserByCPF($cpf))
			$this->ui->addError("CPF já cadastrado!");

		if (($email = $this->ui->getAttributeValue('email')) && $this->userBase->findUserByEmail($email))
			$this->ui->addError("E-mail já cadastrado!");


		$pass1 = $this->ui->getAttributeValue('password');
		$pass2 = $this->ui->getAttributeValue('password_confirmation');
		if ($pass1 != $pass2) {
			$this->ui->addError("Senha e confirmação não casam!");
			return FALSE;
		}

		return (count($this->ui->errors) == 0);
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
