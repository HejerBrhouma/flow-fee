export interface Category {
  id: number;
  name: string;
  icon?: string;
  color?: string;
  editable: boolean;
}

export interface CategoryPayload {
  name: string;
  icon?: string;
  color?: string;
}
