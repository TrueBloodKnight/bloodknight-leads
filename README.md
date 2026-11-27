# BloodKnight.leads

1. Откройте [тестовую веб-форму](http://u222261.test-handyhost.ru/index.php), заполните её и отправьте.
2. Войдите в админку Битрикс, откройте [страницу SQL-запросов](http://u222261.test-handyhost.ru/bitrix/admin/sql.php?PAGEN_1=1&SIZEN_1=20&lang=ru) и выполните запрос.

```sql
SELECT ID, CODE, RESULT_ID, WEBFORM_ID, DATE_CREATED, STATUS_ID, IP
FROM b_bloodknight_lead
ORDER BY ID DESC
LIMIT 10;
```

3. Скопируйте `CODE` новой заявки и подставьте его вместо `#CODE#` в адрес `http://u222261.test-handyhost.ru/lead/#CODE#`.

   Пример адреса — `http://u222261.test-handyhost.ru/lead/674e2748ef0fc8f3ec139a4c1`.

4. Откройте ссылку. Первый переход меняет `STATUS_ID` с `0` на `1`. Повторный показывает сообщение «Заявка уже обработана». Выполнить SQL-запрос ещё раз, чтобы проверить статус.
