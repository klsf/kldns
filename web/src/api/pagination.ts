export interface PageQuery {
  page?: number
  page_size?: number
}

export interface PageResult<T> {
  items: T[]
  total: number
  page: number
  page_size: number
}
