<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    /**
     * El POST será interceptado por el firewall de seguridad,
     * y Symfony verificará las credenciales del usuario.
     * El GET renderiza la pantalla o formulario de ingreso (login)
     */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si ya hay un usuario autenticado, no tiene sentido mostrar el formulario de login
        // Lo redirigimos al listado de tickets
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_ticket_index');
        }

        // Renderizamos el formulario de login
        return $this->render('security/login.html.twig', [
            // Symfony conserva el último identificador/username enviado para repoblarlo
            'last_username' => $authenticationUtils->getLastUsername(),

            // Si el intentó de ingreso falló, el autenticador guarda los datos del error
            'error' => $authenticationUtils->getLastAuthenticationError()
        ]);
    }
}