# yandex-cloud-translate-php

Аутентификация библиотеки в API возможна двумя способами:
1. С помощью oAuth-токена
2. С помощью API-ключа.

Если заданы и oAuth-токен, и API-ключ, то будет использоваться API-ключ.

## oAuth-токен

**ВАЖНО:**  Время жизни oAuth-токена - 1 год, затем его надо обновить.

OAuth-токен необходим для авторизации в Yandex Cloud пользователя с аккаунтом на Яндексе: пользователь обменивает OAuth-токен на IAM-токен.

Получить OAuth-токен для работы с Yandex Cloud можно с помощью запроса
к сервису Яндекс OAuth.

1. [На странице биллинга](https://console.cloud.yandex.ru/billing?section=accounts) убедитесь, что платежный аккаунт находится в статусе ACTIVE или TRIAL_ACTIVE. Если платежного аккаунта нет, создайте его.
2. [Получите идентификатор любого каталога](https://cloud.yandex.ru/docs/resource-manager/operations/folder/get-id), на который у вашего аккаунта есть роль editor или выше.
3. Получите oAuth-токен, необходимый для получения IAM-токенов (сам IAM-токен будет обновляться автоматически в библиотеке): на странице https://cloud.yandex.ru/docs/iam/concepts/authorization/oauth-token перейдите по ссылке для запроса к сервису Яндекс OAuth, 
и на странице отразится токен, который надо записать

```php
$ya = new avadim\YandexCloud\Auth\Auth($oAuthToken);
$tr = new \avadim\YandexCloud\Translator\Translator($ya, $folderId);

var_dump($tr->translate('<span>красная</span> корова', 'en', null, true));
var_dump($tr->getStats());
```

## API-ключ

**ВАЖНО:** Аутентификация через API-ключ возможна только для некоторых сервисов, т.к. этот вариант считается менее безопасным.
Список сервисов можно посмотреть здесь: https://cloud.yandex.ru/docs/iam/concepts/authorization/api-key

Получение ключа:
* В консоли управления выберите каталог, которому принадлежит сервисный аккаунт.
* Перейдите на вкладку __Сервисные аккаунты__.
* Выберите сервисный аккаунт и нажмите на строку с его именем.
* Нажмите кнопку __Создать новый ключ__ на верхней панели.
* Выберите пункт __Создать API-ключ__.
* Выберите алгоритм шифрования.
* Задайте описание ключа, чтобы потом было проще найти его в консоли управления.

```php
$ya = new avadim\YandexCloud\Auth\Auth(null);
$ya->setApiKey($apiKey);
$tr = new \avadim\YandexCloud\Translator\Translator($ya, $folderId);

var_dump($tr->translate('<span>красная</span> корова', 'en', null, true));
var_dump($tr->getStats());
```
