<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\User\UseCase\Create;
use App\Model\User\UseCase\Edit;
use App\Model\User\UseCase\Role;
use App\Model\User\UseCase\SignUp\Confirm;
use App\Model\User\UseCase\Activate;
use App\Model\User\UseCase\Block;
use App\Model\User\Entity\User\User;
use App\ReadModel\User\UserFetcher;
use App\ReadModel\User\Filter;
use App\ReadModel\Work\Members\Member\MemberFetcher;
use DomainException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @IsGranted("ROLE_MANAGE_USERS")
 */
class UsersController extends AbstractController
{
    private const PER_PAGE = 10;

    private $users;
    private $handler;

    public function __construct(UserFetcher $users, ErrorHandler $handler)
    {
        $this->users = $users;
        $this->handler = $handler;
    }

    /**
     * @Route("/users", name="users")
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $filter = new Filter\Filter();

        $form = $this->createForm(Filter\Form::class, $filter);
        $form->handleRequest($request);

        $pagination = $this->users->all(
            $filter,
            $request->query->getInt('page', 1),
            self::PER_PAGE,
            $request->query->get('sort', 'register_date'),
            $request->query->get('direction', 'desc')
        );

        return $this->render('app/users/index.html.twig', [
            'pagination' => $pagination,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/users/create", name="users.create")
     * @param Request $request
     * @param Create\Handler $handler
     * @return Response
     */
    public function create(Request $request, Create\Handler $handler): Response
    {
        $command = new Create\Command();

        $form = $this->createForm(Create\Form::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $handler->handle($command);
                return $this->redirectToRoute('users');
            } catch (DomainException $e) {
                $this->handler->handle($e);
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('app/users/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/users/{id}", name="users.show")
     * @param User $user
     * @param MemberFetcher $members
     * @return Response
     */
    public function show(User $user, MemberFetcher $members): Response
    {
        return $this->render('app/users/show.html.twig', [
            'user' => $user,
            'member' => $members->find($user->getId()->getValue()),
        ]);
    }

    /**
     * @Route("/users/{id}/edit", name="users.edit")
     * @param User $user
     * @param Request $request
     * @param Edit\Handler $handler
     * @return Response
     */
    public function edit(User $user, Request $request, Edit\Handler $handler): Response
    {
        if ($user->getId()->getValue() === $this->getUser()->getId()) {
            $this->addFlash('error', 'Unable to edit yourself.');

            return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
        }

        $command = Edit\Command::fromUser($user);

        $form = $this->createForm(Edit\Form::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $handler->handle($command);

                return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
            } catch (DomainException $e) {
                $this->handler->handle($e);
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('app/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/users/{id}/role", name="users.role")
     * @param User $user
     * @param Request $request
     * @param Role\Handler $handler
     * @return Response
     */
    public function role(User $user, Request $request, Role\Handler $handler): Response
    {
        if ($user->getId()->getValue() === $this->getUser()->getId()) {
            $this->addFlash('error', 'Unable to change role for yourself.');

            return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
        }

        $command = Role\Command::fromUser($user);

        $form = $this->createForm(Role\Form::class, $command);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $handler->handle($command);

                return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
            } catch (DomainException $e) {
                $this->handler->handle($e);
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('app/users/role.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/users/{id}/confirm", name="users.confirm", methods={"POST"})
     * @param User $user
     * @param Request $request
     * @param Confirm\Manual\Handler $handler
     * @return Response
     */
    public function confirm(User $user, Request $request, Confirm\Manual\Handler $handler): Response
    {
        if (!$this->isCsrfTokenValid('confirm', $request->request->get('token'))) {
            return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
        }

        $command = new Confirm\Manual\Command($user->getId()->getValue());

        try {
            $handler->handle($command);
        } catch (DomainException $e) {
            $this->handler->handle($e);
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
    }

    /**
     * @Route("users/{id}/activate", name="users.activate", methods={"POST"})
     * @param User $user
     * @param Request $request
     * @param Activate\Handler $handler
     * @return Response
     */
    public function activate(User $user, Request $request, Activate\Handler $handler): Response
    {
        if (!$this->isCsrfTokenValid('activate', $request->request->get('token'))) {
            return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
        }

        $command = new Activate\Command($user->getId()->getValue());

        try {
            $handler->handle($command);
        } catch (DomainException $e) {
            $this->handler->handle($e);
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
    }

    /**
     * @Route("users/{id}/block", name="users.block", methods={"POST"})
     * @param User $user
     * @param Request $request
     * @param Block\Handler $handler
     * @return Response
     */
    public function block(User $user, Request $request, Block\Handler $handler): Response
    {
        if (!$this->isCsrfTokenValid('block', $request->request->get('token'))) {
            return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
        }

        if ($user->getId()->getValue() === $this->getUser()->getId()) {
            $this->addFlash('error', 'Unable to block yourself.');
            return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
        }

        $command = new Block\Command($user->getId()->getValue());

        try {
            $handler->handle($command);
        } catch (DomainException $e) {
            $this->handler->handle($e);
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('users.show', ['id' => $user->getId()]);
    }
}
