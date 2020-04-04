<?php
namespace App\Controller;

use Neos\Flow\Annotations as Flow;
use App\Service\Foo;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LuckyController
{


    /**
     * @Flow\Inject
     * @var Foo
     */
    protected $foo;

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
