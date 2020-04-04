<?php
namespace App\Controller;

use App\Service\Foo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LuckyController
{

    public function __construct(Foo $foo) {
        $foo->call();
    }

    /**
     * @Route("/lucky/", name="app_lucky_number")
     */
    public function number()
    {
        $number = random_int(0, 10);

        return new Response(
            '<html><body>Lucky number: '.$number.'</body></html>'
        );
    }
}
