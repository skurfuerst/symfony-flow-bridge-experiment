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


- AOP
    - Unplanned extensibility: "I want to override a certain method ("anywhere") -> "Patching escape hatch" (see this repo)
    - Security Framework in Controllers -> Symfony Security
    - Security Framework in Entities -> could be possible via Doctrine Proxies??
    - Security Framework ANYWHERE -> DO WE NEED TO SUPPORT THIS??
- Dependency Injection -> Service Container + X
    - DI in Prototypes -> do not recommend in the long term; we can do that; but maybe not recommended?
    - DI in Singletons via Flow\Inject -> we could do this via this prototype; TODO maybe do not use this in Neos core, but use Constructor Injection instead 
    - get all instances of an interface -> Tagged Services
    - get all instances of an annotated class -> TODO replace via marker interfaces 
- Cache Framework -> Symfony Cache
- Validation -> Symfony Validation
- Eel -> Symfony EL
- Flow MVC -> Symfony MVC
- Fluid ?? TODO UNSURE
- Signals / Slots -> Event Dispatcher
- Flow conventions -> Symfony Flex conventions
- Resource Management -> 
- Translation -> Intl
- Flow Security -> Symfony Security
- Property Mapper -> Drop (Serializer)

- In Neos Core 
- Flow Inject to Constructor Injection


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