# JWT Refresh Token
JWT Refresh Token jest to plugin Wordpress rozszerzający możliwości wtyczki [JWT Auth](https://wordpress.org/plugins/jwt-auth/) stworzonej przez [Useful Team](https://usefulteam.com/).

## JWT Auth
JWT Auth dodaje do strony 2 punkty API:
* /wp-json/jwt-auth/v1/token
* /wp-json/jwt-auth/v1/token/validate

Pierwszy z nich pozwala zalogować się do strony za pomocą zapytania `POST`. Jeżeli użytkownik istnieje w bazie, zapytanie zwróci tymczasowy `JSON Web Token (JWT)`, który aplikacja może wykorzystywać w dalszej komunikacji.

Drugi punkt API pozwala zweryfikować wygenerowany wcześniej token JWT.

## JWT Refresh Token
JWT Refresh Token rozszerza możliwości podstawowej wtyczki o funkcje:
* Automatycznego odświeżania tokena JWT (przedłuzania ważności).
* Rejestrowania nowych kont użytkowników.
* Resetowania hasła użytkowników.

**Odświeżanie** odbywa się automatycznie, wraz z logowaniem. Po wygenerowaniu podstawowego tokena JWT, strona zapisuje w przeglądarce ciasteczko `Secure Cookie` zawierające Refresh Token podobny do JWT. Wysłanie zapytania `GET` pod adres `/jwt-auth/v1/token/refresh` automatycznie sprawdzi bezpieczne ciasteczko i wymieni token na nowy, bez konieczności ponownego logowania. 

Dzięki takiemu mechanizmowi, podstawowy token JWT zachowuje ważność jedynie przez 15 min i może być przechowywany w dowolnej formie w pamięci podręcznej. Bezpieczne ciasteczko zawierające Refresh Token nie jest możliwe do odzyskania przez złośliwe skrypty JS.

**Rejestrowanie użytkowników** polega na wysłaniu zapytania `POST` pod adres `/jwt-auth/v1/register-user`. Zapytanie musi zawierać `username`, `email` oraz `password`. Strona automatycznie utworzy konto użytkownika i odeśle nowy token JWT.

**Resetowanie hasła** wymaga wysłania zapytania `POST` pod adres `/jwt-auth/v1/reset-password` zawierającego `email` użytkownika. Po sprawdzeniu danych, Wordpress wyśle link do zresetowania hasła dla wskazanego użytkownika. Link resetujący hasło umożliwia przekierowanie użytkownika do niestandardowego formularza ustawiania hasła. Przekierowanie zachowuje jednak te same standardy bezpieczeństwa co standardowy formularz Wordpress.

Głównym celem wtyczki jest udostępnienie mechanizmu logowania i komunikacji dla stron Single Page Application napisanych z użyciem biblioteki React, Vue, czy Angular.
