<?php


error_reporting(E_ALL | E_STRICT);

# Magic quoting sucks.
function unquote($txt) {
	if (get_magic_quotes_gpc())
		return stripslashes($txt);
	else
		return $txt;
}

class User {
	public $cpf;       # String; primary key (não cabe num int, nem inventem)
	public $name;      # String
	public $address;   # String
	public $phone;     # String
	public $email;     # String
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
}

class ProductBase extends DataBase{
	// µµµ.
}

function getCurrentAuthData() {
	if ($_REQUEST['email'])
		return (object) Array('email' => $_REQUEST['email'], 'password' => $_REQUEST['password']);
	else
		return NULL;
}

class Session {
	public $user;
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

		if (isset($_REQUEST['action']))
			$action = $_REQUEST['action'];
		else
			$action = 'home';

		switch ($action) {
			case 'validateLogin':
				$this->validateLogin();
				break;
			case 'home':
				echo "Home!\n";
				break;
			default:
				echo "WTF is $action?\n";
		}

	}

	function validateLogin() {
		echo "No!\n";
	}
}


?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<HTML>
<HEAD>
	<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=UTF-8">
	<TITLE>µµµ</TITLE>
</HEAD>
<BODY>
<?php

$main = new MainController();
$main->init();

?>
</BODY>
</HTML>