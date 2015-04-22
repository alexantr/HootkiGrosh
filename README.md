# HootkiGrosh

Класс для работы с API сервиса «Хуткi Грош»

[Подробное описание API (pdf)](http://hutkigrosh.by/Docs/API%20сервиса%20Хуткi%20Грош.ru.pdf)

## Установка

Установка через [Composer](http://getcomposer.org/):

```
composer require alexantr/hootkigrosh "~1.0@dev"
```

## Пример использования

Инициализация:

```php
require 'HootkiGrosh.php';

$user = 'user@org.com'; // имя пользователя
$pwd = 'paSSwo_rd'; // пароль
$is_test = true; // тестовый api

$hg = new \Alexantr\HootkiGrosh\HootkiGrosh($is_test);
```

Авторизация:

```php
$res = $hg->apiLogIn($user, $pwd);

// Ошибка авторизации
if (!$res) {
    echo $hg->getError();
    $hg->apiLogOut(); // Завершаем сеанс
    exit;
}
```

Добавление нового счета в систему:

```php
$data = array(
    'eripId' => '40000001',
    'invId' => 'C-1234',
    'fullName' => 'Пупкин Василий Иванович',
    'mobilePhone' => '+333 33 3332221',
    'email' => 'pupkin@org.com',
    'fullAddress' => 'г.Минск, пр.Победителей, д.1, кв.1',
    'amt' => 120000,
    'products' => array(
        array(
            'invItemId' => 'Артикул 123',
            'desc' => 'Услуга, за которую производят оплату',
            'count' => 1,
            'amt' => 119000,
        ),
        array(
            'invItemId' => '-нет-',
            'desc' => 'Доставка',
            'count' => 1,
            'amt' => 1000,
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
```

Статус счета:

```php
$status = $hg->apiBillStatus($billID);
if (!$status) {
    echo $hg->getError();
    $hg->apiLogOut(); // Завершаем сеанс
    exit;
}
echo 'Статус: ' . $status . ' (' . $hg->getPurchItemStatus($status) . ')<br>';
```

Информация о счете:

```php
$info = $hg->apiBillInfo($billID);
if (!$info) {
    echo $hg->getError();
    $hg->apiLogOut(); // Завершаем сеанс
    exit;
}
echo '<pre>' . print_r($info, true) . '</pre>';
```

Удаление счета:

```php
$res = $hg->apiBillDelete($billID);
if (!$res) {
    echo $hg->getError();
    $hg->apiLogOut(); // Завершаем сеанс
    exit;
}
```

Дамп ответа:

```php
$response = $hg->getResponse();
echo '<hr><pre>' . htmlspecialchars($response) . '</pre>';
```

Завершение сеанса:

```php
$res = $hg->apiLogOut();
```
