# JWT Refresh Token
Plugin wordpress bazujący na wtyczce JWT Auth.

Rozszerza podstawowe funkcje JWT Auth o dodatkowe endpointy, pozwalające na:
* Wygenerowanie dodatkowego refresh-token przechowywanego w przeglądarce jako secure cookie
* Utworzenie nowego konta użytkownika
* Ustawienie własnego formularza resetowania hasła użytkownika, zamiast wordpressowego

Głównym celem wtyczki jest udostępnienie mechanizmu logowania dla aplikacji SPA (np. React).
