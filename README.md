# JWT Refresh Token
Plugin wordpress bazujący na wtyczce [JWT Auth](https://wordpress.org/plugins/jwt-auth/) autorstwa [Useful Team](https://usefulteam.com/).

Podstawowa wtyczka JWT Auth dodaje 2 endpointy do strony opartej na wordpressie:
* mysite.com/wp-json/jwt-auth/v1/token
* mysite.com/wp-json/jwt-auth/v1/token/validate

Aplikacja klienta może wysłać żądanie `POST`, zawierające pola `username` oraz `password` pod adres `/jwt-auth/v1/token`. Jeżeli użytkownik istnieje w bazie danych, Wordpress zwróci tymczasowy `Javascript Web Token (JWT)`, który aplikacja może od tej pory wykorzystywać do poruszania się po pozostałych endpointach.

JWT Refresh Token rozszerza możliwości podstawowej wtyczki o dodatkowe funkcje:
* Po wygenerowaniu podstawowego JWT Token zapisuje w przeglądarce `Secure Cookie` zawierające Refresh Token. Dzięki temu wysłanie zapytania `GET` pod adres `/jwt-auth/v1/token/refresh` automatycznie wymieni JWT Token na nowy, bez konieczności ponownego logowania.
* Wysłanie zapytania `POST` pod adres `/jwt-auth/v1/register-user` zawierającego `username`, `email` oraz `password` utworzy nowe konto użytkownika.
* Wysłanie zapytania `POST` pod adres `/jwt-auth/v1/reset-password` zawierającego `email`, wyśle link do zresetowania hasła dla użytkownika.

Link resetujący hasło umożliwia przekierowanie użytkownika do niestandardowego formularza zmiany hasła, jednak wciąż zachowuje te same reguły bezpieczeństwa, co standardowy formularz wordpress.

Głównym celem wtyczki jest udostępnienie mechanizmu logowania dla aplikacji Single Page Application działających jako podstrona Wordpressa lub w innej domenie (np. React).
