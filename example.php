<?php

$user = 'user@org.com'; // имя пользователя
$pwd = 'paSSwo_rd'; // пароль
$is_test = true; // тестовый api

require 'HootkiGrosh.php';

$hg = new HootkiGrosh($is_test);

/*
 * Авторизация
 */

$res = $hg->apiLogIn($user, $pwd);

// Ошибка авторизации
if (!$res) {
	echo $hg->getError();
	$hg->apiLogOut(); // Завершаем сеанс
	exit;
}

/*
 * Добавляем новый счет в систему
 */

$data = array(
	'eripId'      => '40000001',
	'invId'       => 'C-1234',
	'fullName'    => 'Пупкин Василий Иванович',
	'mobilePhone' => '+333 33 3332221',
	'email'       => 'pupkin@org.com',
	'fullAddress' => 'г.Минск, пр.Победителей, д.1, кв.1',
	'amt'         => 120000,
	'products'    => array(
		array(
			'invItemId' => 'Артикул 123',
			'desc'      => 'Услуга, за которую производят оплату',
			'count'     => 1,
			'amt'       => 119000,
		),
		array(
			'invItemId' => '-нет-',
			'desc'      => 'Доставка',
			'count'     => 1,
			'amt'       => 1000,
		),
	),
);

$billID = $hg->apiBillNew($data);
if (!$billID) {
	echo $hg->getError();
	$hg->apiLogOut(); // Завершаем сеанс
	exit;
}
echo 'bill ID: ' . $billID . '<br>';

/*
 * Статус счета
 */

$status = $hg->apiBillStatus($billID);
if (!$status) {
	echo $hg->getError();
	$hg->apiLogOut(); // Завершаем сеанс
	exit;
}
echo 'Статус: ' . $status . ' (' . $hg->getPurchItemStatus($status) . ')<br>';

/*
 * Информация о счете
 */

$info = $hg->apiBillInfo($billID);
if (!$info) {
	echo $hg->getError();
	$hg->apiLogOut(); // Завершаем сеанс
	exit;
}
echo '<pre>' . print_r($info, true) . '</pre>';

/*
 * Удаление счета
 */

$res = $hg->apiBillDelete($billID);
if (!$res) {
	echo $hg->getError();
	$hg->apiLogOut(); // Завершаем сеанс
	exit;
}

/*
 * Raw response
 */

$response = $hg->getResponse();
echo '<hr><pre>' . htmlspecialchars($response) . '</pre>';

/*
 * Завершаем сеанс
 */

$res = $hg->apiLogOut();
