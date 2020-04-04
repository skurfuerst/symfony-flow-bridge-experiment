<?php
namespace App\Controller;

use App\Service\Foo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LuckyController
{


    /**
     * @var Foo
     */
    protected $foo;

    public function __construct(Foo $foo) {
        $this->foo = $foo;
    }

    /**
     * @Route("/lucky/", name="app_lucky_number")
     */
    public function number()
    {
        $this->foo->call();
        $number = random_int(0, 10);

        return new Response(
            '<html><body>Lucky number: '.$number.'</body></html>'
        );
    }
}
