In one terminal window, run this server, using:
```
mkdir vendor
touch vendor/autoload.php
php -S 8000
```

Then in another window POST a SOAP message to it, for instance from https://github.com/pondersource/peppol-php/tree/main/docs/rules/examples:
```
curl -T ../peppol-php/docs/rules/examples/completeMessage -H "Content-Type: application/soap+xml" http://localhost:8000```