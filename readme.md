# Area Core

A PHP Framework for Web & CLI Application Development.

## AMR Architecture

This project uses the AMR architecture pattern. AMR stands for:

- A = Action
- M = Model
- R = Responder

Overview
--------

AMR is a small, pragmatic pattern that clarifies responsibilities in request handling by separating orchestration (Action), business logic and data (Model), and presentation/response formatting (Responder). It is especially useful for applications that must expose the same use cases across multiple interfaces (HTTP controllers, APIs, CLI commands, background jobs).

Roles
-----

- Action: receives an input (for example an HTTP request or CLI arguments), performs request-level concerns (authentication, authorization, input validation, and orchestration), invokes one or more Models to perform business logic, and selects or prepares a Responder.

- Model: encapsulates business rules, domain logic and data access. Models should be framework-agnostic where possible — they return plain data or domain objects and do not know about HTTP or presentation details.

- Responder: formats the final output for the target interface. For web requests this might produce an HTTP response, headers and a view/template or JSON. For CLI it may print formatted text. The Responder keeps presentation concerns out of Actions and Models.

Typical request flow
--------------------

1. A transport adapter (router, controller, CLI runner) routes the request to an Action.
2. The Action validates input and invokes Models to execute business logic.
3. The Models return data or domain objects to the Action.
4. The Action passes data to a Responder, which builds the final response (HTTP response, JSON body, plain text, etc.).
5. The transport sends the Responder's output back to the client.

Benefits
--------

- Strong separation of concerns: orchestration, business logic and presentation are distinct.
- Easy to test: Actions can be tested by mocking Models and Responders; Models can be tested in isolation.
- Flexible outputs: the same Action/Model combination can be paired with different Responders to support APIs, HTML pages and CLI without duplicating business logic.
- Predictable flow: developers quickly understand where to add validation, business rules or formatting.

Implementation tips
-------------------

- Keep Actions thin: they should not contain heavy business logic. Instead, call Model services or domain objects.
- Keep Models free of transport concerns: avoid returning HTTP codes from Models; return domain results or exceptions.
- Keep Responders responsible only for presentation: building HTTP responses, serializing JSON, or rendering templates.
- Use small value objects or plain arrays for data transfer between layers to make unit testing easier.

Small PHP pseudo-example
------------------------
```php
Action (orchestrates request)
class CreateUserAction {
	public function __construct(UserModel $model, CreateUserResponder $responder) {}

	public function __invoke(array $input) {
		$validated = $this->validate($input);
		$user = $this->model->create($validated);
		return $this->responder->respond($user);
	}
}

// Model (business logic & data)
class UserModel {
	public function create(array $data) { /* persist and return domain object */ }
}

// Responder (presentation)
class CreateUserResponder {
	public function respond($user) { /* return HTTP response or CLI output */ }
}
```


AMR vs MVC
----------

AMR focuses on a single-use-case flow: Action (use case orchestration), Model (domain logic) and Responder (presentation). MVC splits responsibilities differently and often mixes view rendering inside controllers; AMR makes the use-case explicit and encourages reusable Models and interchangeable Responders.

When to use
-----------

AMR is a good fit for applications that need clear, testable use-case boundaries and multiple output formats. It's also helpful when you want to keep controllers thin and avoid mixing presentation with business logic.

Further reading & notes
----------------------

This README is a short overview. Keep Actions, Models and Responders small and focused. Prefer composition over inheritance and write unit tests for each component in isolation.




