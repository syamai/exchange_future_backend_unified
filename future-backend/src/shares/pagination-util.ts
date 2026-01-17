import { PaginationDto } from "src/shares/dtos/pagination.dto";

export const getQueryLimit = (
  paginationDto: PaginationDto,
  maxResultCount?: number
): { offset: number; limit: number } => {
  // const offset = Math.min(paginationDto.size * (paginationDto.page - 1), maxResultCount);
  const offset = paginationDto.size * (paginationDto.page - 1);
  const limit = paginationDto.size;
  // if (offset + limit > maxResultCount) {
  //   limit = Math.max(maxResultCount - offset, 0);
  // }

  return { offset, limit };
};
