<?php

declare(strict_types=1);

namespace App\UI\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatusController extends AbstractController
{
    #[Route('/', name: 'root')]
    #[Route('/status', name: 'status')]
    #[Route('/health', name: 'health')]
    public function index(): Response
    {
        return new Response(null, Response::HTTP_OK);
    }
}
