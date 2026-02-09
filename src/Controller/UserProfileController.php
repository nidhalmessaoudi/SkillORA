<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class UserProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('', name: 'user_profile')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        return $this->render('pages/profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'user_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $error = null;
        $success = false;

        if ($request->isMethod('POST')) {
            $firstName = $request->request->get('first_name');
            $lastName = $request->request->get('last_name');
            $email = $request->request->get('email');
            $currentPassword = $request->request->get('current_password');
            $newPassword = $request->request->get('new_password');

            // Validate input
            if (!$firstName || !$lastName || !$email) {
                $error = 'First name, last name, and email are required.';
            } else {
                // Check if email is already taken by another user
                $existingUser = $this->entityManager->getRepository(User::class)
                    ->createQueryBuilder('u')
                    ->where('u.email = :email')
                    ->andWhere('u.id != :id')
                    ->setParameter('email', $email)
                    ->setParameter('id', $user->getId())
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($existingUser) {
                    $error = 'This email is already in use by another account.';
                } else {
                    // Update user info
                    $user->setFirstName($firstName);
                    $user->setLastName($lastName);
                    $user->setEmail($email);

                    // Update password if provided
                    if ($newPassword) {
                        if (!$currentPassword) {
                            $error = 'Current password is required to set a new password.';
                        } elseif (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                            $error = 'Current password is incorrect.';
                        } else {
                            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
                            $user->setPassword($hashedPassword);
                        }
                    }

                    if (!$error) {
                        $this->entityManager->flush();
                        $success = true;
                        $this->addFlash('success', 'Profile updated successfully!');
                    }
                }
            }
        }

        return $this->render('pages/profile/edit.html.twig', [
            'user' => $user,
            'error' => $error,
            'success' => $success,
        ]);
    }

    #[Route('/settings', name: 'user_profile_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $error = null;
        $success = false;

        if ($request->isMethod('POST')) {
            $phone = $request->request->get('phone');
            $countryCode = $request->request->get('country_code');
            $gender = $request->request->get('gender');
            $dateOfBirth = $request->request->get('date_of_birth');
            $bio = $request->request->get('bio');
            $fieldOfStudy = $request->request->get('field_of_study');
            $university = $request->request->get('university');
            $country = $request->request->get('country');

            // Handle avatar upload
            $avatarFile = $request->files->get('avatar');
            if ($avatarFile) {
                // Validate file size
                $maxSize = 2 * 1024 * 1024; // 2MB

                if ($avatarFile->getSize() > $maxSize) {
                    $error = 'File size must be less than 2MB.';
                } else {
                    // Get original extension from client filename
                    $originalName = $avatarFile->getClientOriginalName();
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    
                    // Validate file extension
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                    if (!in_array($extension, $allowedExtensions)) {
                        $error = 'Invalid file type. Please upload JPG, PNG, or GIF.';
                    } else {
                        // Create uploads directory if it doesn't exist
                        $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';
                        if (!file_exists($uploadsDir)) {
                            mkdir($uploadsDir, 0777, true);
                        }

                        // Generate unique filename with original extension
                        $fileName = uniqid() . '_' . time() . '.' . $extension;
                        
                        try {
                            // Move uploaded file
                            $avatarFile->move($uploadsDir, $fileName);
                            
                            // Delete old avatar if exists
                            if ($user->getAvatar()) {
                                $oldFile = $uploadsDir . '/' . $user->getAvatar();
                                if (file_exists($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }
                            
                            // Update user avatar
                            $user->setAvatar($fileName);
                        } catch (\Exception $e) {
                            $error = 'Failed to upload avatar. Please try again: ' . $e->getMessage();
                        }
                    }
                }
            }

            // Combine country code with phone number
            $fullPhone = $phone ? $countryCode . ' ' . $phone : null;

            // Update additional profile fields
            $user->setPhone($fullPhone);
            $user->setGender($gender);
            $user->setBio($bio);
            $user->setFieldOfStudy($fieldOfStudy);
            $user->setUniversity($university);
            $user->setCountry($country);

            // Parse date of birth
            if ($dateOfBirth) {
                try {
                    $dob = new \DateTime($dateOfBirth);
                    $user->setDateOfBirth($dob);
                } catch (\Exception $e) {
                    $error = 'Invalid date format';
                }
            }

            // Mark profile as completed if enough fields are filled
            $filledFields = 0;
            if ($phone) $filledFields++;
            if ($gender) $filledFields++;
            if ($dateOfBirth) $filledFields++;
            if ($fieldOfStudy) $filledFields++;
            
            if ($filledFields >= 3) {
                $user->setProfileCompleted(true);
            }

            if (!$error) {
                $this->entityManager->flush();
                $success = true;
                $this->addFlash('success', 'Settings updated successfully!');
            }
        }

        return $this->render('pages/profile/settings.html.twig', [
            'user' => $user,
            'error' => $error,
            'success' => $success,
        ]);
    }

    #[Route('/delete', name: 'user_profile_delete', methods: ['POST'])]
    public function delete(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $password = $request->request->get('password');

        // Verify password before deletion
        if (!$password || !$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Incorrect password. Account not deleted.');
            return $this->redirectToRoute('user_profile_edit');
        }

        // Prevent admin deletion
        if ($user->isAdmin()) {
            $this->addFlash('error', 'Admin accounts cannot be deleted.');
            return $this->redirectToRoute('user_profile_edit');
        }

        // Delete user
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        // Logout and redirect
        $request->getSession()->invalidate();
        $this->addFlash('success', 'Your account has been deleted.');
        
        return $this->redirectToRoute('app_home');
    }
}
