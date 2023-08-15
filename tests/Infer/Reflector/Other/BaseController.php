<?php

	namespace Dedoc\Scramble\Tests\Infer\Reflector\Other;

	use App\Http\Controllers\Controller;
    use App\Repositories\Repository;
    use Exception;
    use Illuminate\Http\Request;
    use Illuminate\Http\Response;
    use Illuminate\Support\Facades\App;
    use Symfony\Component\HttpFoundation\Response as ResponseAlias;

    abstract class BaseController
	{
		protected Repository $repository;

		public function index(Request $request): Response
		{
			$query = $request->input('query', null);
			$perPage = $request->input('perPage', 10);
			$sort = $request->input('sort', 'id');
			$order = $request->input('order', 'ASC');

			$searchColumns = $this->searchColumns() ?? [];
			$queryParams = $request->only($searchColumns);
			$whereHasRelations = $this->whereHasRelations() ?? [];

			$selectColumns = $this->select() ?? ['*'];
			$relations = $this->relations() ?? [];
			$countRelations = $this->countRelations() ?? [];
			$whereConditions = $this->where();
			$selectRelations = $this->selectRelations() ?? [];
			$searchFields = $this->searchColumns() ?? [];

			$baseQuery = $this->repository->select($selectColumns);


			if ($queryParams) {
				foreach ($queryParams as $column => $value) {
					if (strpos($value, '-') !== false) { // if we have a range
						list($min, $max) = explode('-', $value); // split on the dash
						$baseQuery->whereBetween($column, [$min, $max]); // add a between clause
					} else {
						$baseQuery->whereIn($column, explode(',', $value));
					}
				}
			}
			foreach ($whereHasRelations as $relation => $callback) {
				$baseQuery->whereHas($relation, $callback);
			}
			$baseQuery->whereConditions($whereConditions ?? []);
			$data = $baseQuery->all($perPage, $query, $relations, $countRelations, $sort, $order, $searchFields, $selectRelations);

			return $this->sendResponse($data, ResponseAlias::HTTP_OK);
		}

		public function show($id): Response
		{
			try {
				$whereHasRelations = $this->whereHasRelations() ?? [];
				$selectRelations = $this->selectRelations() ?? [];
				$baseQuery = $this->repository->select($this->select() ?? ['*']);
				foreach ($whereHasRelations as $relation => $callback) {
					$baseQuery->whereHas($relation, $callback);
				}
				$data = $baseQuery->whereConditions($this->where() ?? [])->find($id, $this->relations() ?? [], $this->countRelations() ?? [], $selectRelations);

				return $this->sendResponse($data, ResponseAlias::HTTP_OK);
			} catch (Exception $e) {
				return $this->sendError($e->getMessage(), ResponseAlias::HTTP_NOT_FOUND);
			}
		}


		// This function will be called before storing data.
		protected function beforeStore(array $data): array
		{
			// By default, just return the same data.
			// Override this method in derived controllers to implement custom logic.
			return $data;
		}

		public function store(Request $request): Response
		{


			try {
				$formRequest = App::make($this->formRequest());
				$request->replace($formRequest->validated());

				$dataToStore = $this->beforeStore($request->all());
				$model = $this->repository->create($dataToStore);

				// Saving relationship data
				foreach ($dataToStore as $key => $value) {
					if (!in_array($key, $model->getFillable()) && $model->relationLoaded($key)) {
						$model->$key()->createMany($value);
					}
				}

				return $this->sendResponse($model->load($model->getRelations()), ResponseAlias::HTTP_CREATED);

			} catch (Exception $e) {
				return $this->sendError($e->getMessage(), ResponseAlias::HTTP_BAD_REQUEST);
			}
		}

		// This function will be called before updating data.
		protected function beforeUpdate($id,array $data): array
		{
			// By default, just return the same data.
			// Override this method in derived controllers to implement custom logic.
			return $data;
		}

		public function update(int $id,Request $request): Response
		{
			try {
				$formRequest = App::make($this->formRequest());
				$request->replace($formRequest->validated());

				$dataToUpdate = $this->beforeUpdate($id,$request->all());
				if (empty($dataToUpdate)) {
					return $this->sendError('No data to update', ResponseAlias::HTTP_BAD_REQUEST);
				}
				$this->repository->update($id, $dataToUpdate);
				$model = $this->repository->find($id);
				// Updating relationship data
				foreach ($dataToUpdate as $key => $value) {
					if (!in_array($key, $model->getFillable()) && $model->relationLoaded($key)) {
						// Detaching all existing relationships before re-attaching
						$model->$key()->detach();

						// Re-attaching with new data
						$model->$key()->createMany($value);
					}
				}

				return $this->sendResponse($model->load($model->getRelations()), ResponseAlias::HTTP_CREATED);

			} catch (Exception $e) {
				return $this->sendError($e->getMessage(), ResponseAlias::HTTP_BAD_REQUEST);
			}
		}

		protected function beforeDestroy(int $id): bool
		{
			// By default, just return true.
			// Override this method in derived controllers to implement custom logic.
			return true;
		}
		public function destroy(int $id)
		{
			if (!$this->beforeDestroy($id)) {
				return $this->sendError('Cannot delete this record', ResponseAlias::HTTP_BAD_REQUEST);
			}
			$deleted = $this->repository->delete($id);

			return empty($deleted) ? $this->sendResponse($deleted, ResponseAlias::HTTP_NO_CONTENT) : $this->sendResponse($deleted, ResponseAlias::HTTP_OK);
		}

		public function sendResponse($result, int $httpResponseCode): Response
		{
			return response(['success' => true, 'data' => $result,], $httpResponseCode);
		}

		public function sendError(string $error, int $httpResponseCode, array $errorDetails = []): Response
		{
			$response = ['success' => false, 'message' => $error,];

			if (!empty($errorDetails)) {
				$response['data'] = $errorDetails;
			}

			return response($response, $httpResponseCode);
		}


		protected function select(): array
		{
			return ['*'];
		}

		protected function selectRelations(): array
		{
			return [];
		}


		protected function searchColumns()
		{
			return [];
		}

		abstract protected function formRequest():string;



		protected function relations()
		{
			return [];
		}

		protected function countRelations()
		{
			return [];
		}

		protected function where()
		{
			return [];
		}

		protected function whereHasRelations()
		{
			return [];
		}
	}
