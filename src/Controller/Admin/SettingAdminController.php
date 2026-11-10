<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class SettingAdminController extends AbstractController
{
    #[Route('/settings', name: 'admin_settings')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            if ($action === 'update_profile' && $user instanceof User) {
                $email = trim($request->request->get('email', ''));
                $user->setEmail($email ?: null);
                $em->flush();
                $this->addFlash('success', 'Admin profile email updated.');
                return $this->redirectToRoute('admin_settings');
            }
        }

        return $this->render('admin/settings.html.twig', [
            'user' => $user,
            'activePage' => 'settings',
            'topbarTitle' => 'System & Admin Settings',
        ]);
    }

    #[Route('/settings/password', name: 'admin_password_change', methods: ['POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_password_change', $token)) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_settings');
        }

        $currentPass = $request->request->get('current_password', '');
        $newPass = $request->request->get('new_password', '');
        $confirm = $request->request->get('confirm_password', '');

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'No authenticated administrator found.');
            return $this->redirectToRoute('admin_settings');
        }

        if (!$passwordHasher->isPasswordValid($user, $currentPass)) {
            $this->addFlash('error', 'Current administrator password is incorrect.');
            return $this->redirectToRoute('admin_settings');
        }

        if (strlen($newPass) < 6) {
            $this->addFlash('error', 'New password must be at least 6 characters long.');
            return $this->redirectToRoute('admin_settings');
        }

        if ($newPass !== $confirm) {
            $this->addFlash('error', 'New password and confirmation do not match.');
            return $this->redirectToRoute('admin_settings');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $newPass));
        $em->flush();

        $this->addFlash('success', 'Admin password updated securely with bcrypt hashing.');
        return $this->redirectToRoute('admin_settings');
    }
}
