<?php
namespace App\Controller;

use Skurfuerst\ComposerProxyGenerator\Annotations as Flow;
use App\Service\Foo;
use Skurfuerst\ComposerProxyGenerator\Api\FlowAnnotationAware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LuckyController implements FlowAnnotationAware
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
            '<html><body>Luckysa number: '.$number.'</body></html>'
        );
    }
}
