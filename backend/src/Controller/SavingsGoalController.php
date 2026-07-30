<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\SavingsGoal;
use App\Entity\User;
use App\Repository\SavingsGoalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/savings-goals', name: 'api_savings_goal_')]
class SavingsGoalController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SavingsGoalRepository $savingsGoalRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $goals = $this->savingsGoalRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->json(
            json_decode($this->serializer->serialize($goals, 'json', ['groups' => ['savings_goal:read']]))
        );
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $goal = new SavingsGoal();
        $goal->setUser($user);
        $goal->setName($data['name'] ?? '');
        $goal->setTargetAmount((string) ($data['targetAmount'] ?? '0'));
        $goal->setCurrency($data['currency'] ?? 'EUR');

        if (!empty($data['targetDate'])) {
            $goal->setTargetDate(new \DateTime($data['targetDate']));
        }

        $errors = $this->validator->validate($goal);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($goal);
        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($goal, 'json', ['groups' => ['savings_goal:read']])),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(SavingsGoal $goal): JsonResponse
    {
        $this->assertOwner($goal);

        return $this->json(
            json_decode($this->serializer->serialize($goal, 'json', ['groups' => ['savings_goal:read']]))
        );
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(SavingsGoal $goal, Request $request): JsonResponse
    {
        $this->assertOwner($goal);

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $goal->setName($data['name']);
        }
        if (isset($data['targetAmount'])) {
            $goal->setTargetAmount((string) $data['targetAmount']);
        }
        if (isset($data['currency'])) {
            $goal->setCurrency($data['currency']);
        }
        if (array_key_exists('targetDate', $data)) {
            $goal->setTargetDate($data['targetDate'] ? new \DateTime($data['targetDate']) : null);
        }

        $errors = $this->validator->validate($goal);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($goal, 'json', ['groups' => ['savings_goal:read']]))
        );
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(SavingsGoal $goal): JsonResponse
    {
        $this->assertOwner($goal);

        $this->em->remove($goal);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/contribute', name: 'contribute', methods: ['POST'])]
    public function contribute(SavingsGoal $goal, Request $request): JsonResponse
    {
        $this->assertOwner($goal);

        $data = json_decode($request->getContent(), true);
        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            return $this->json(['message' => 'Le montant doit être positif.'], Response::HTTP_BAD_REQUEST);
        }

        $wasReached = (float) $goal->getCurrentAmount() >= (float) $goal->getTargetAmount();

        $goal->setCurrentAmount((string) ((float) $goal->getCurrentAmount() + $amount));
        $this->em->flush();

        $isReached = (float) $goal->getCurrentAmount() >= (float) $goal->getTargetAmount();

        if ($isReached && !$wasReached) {
            $notification = new Notification();
            $notification->setUser($goal->getUser());
            $notification->setType(Notification::TYPE_SAVINGS_GOAL_REACHED);
            $notification->setMessage(sprintf('Bravo, vous avez atteint votre objectif "%s" !', $goal->getName()));
            $notification->setData(['savingsGoalId' => $goal->getId()]);
            $this->em->persist($notification);
            $this->em->flush();
        }

        return $this->json(
            json_decode($this->serializer->serialize($goal, 'json', ['groups' => ['savings_goal:read']]))
        );
    }

    private function assertOwner(SavingsGoal $goal): void
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($goal->getUser()?->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
