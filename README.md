# Experiment of building Neos Flow with Symfony

* Dependency Injection
    * Constructor Injection y y
    * Setter Injection y y
    * Public Property Injection y y
    * Protected Property Injection via Flow\Inject y n
* MVC y y
* AOP y n
* Eel - Symfony EL



https://github.com/steevanb/composer-overload-class



https://github.com/olvlvl/symfony-dependency-injection-proxy



https://symfony.com/doc/current/service_container/lazy_services.html
https://github.com/Ocramius/ProxyManager




- Dependency Injection einfacher
    - -> Flow\Inject
    - -> "new" injection.
- AOP -> komplexer: "non-local" proxy classes

### Grundannahmen

- AOP muss noch global gehen als "Escape Hatch"
- AOP kann "komplizierter" konfiguriert werden
- Flow\Inject soll auch in mit new() angelegten Klassen funktionieren

####


- 1) idee: AOP anmeldung in composer.json
- 2) 