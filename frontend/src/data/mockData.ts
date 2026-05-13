export type ComplaintStatus = "Pending" | "Under Investigation" | "Resolved";
export type ComplaintCategory = "Noise" | "Theft" | "Domestic" | "Property" | "Others";

export interface Complaint {
  id: number;
  trackingNumber: string;
  residentName: string;
  contactMethod: "phone" | "email";
  contactValue: string;
  contactNumber: string;
  category: ComplaintCategory;
  description: string;
  status: ComplaintStatus;
  dateSubmitted: string;
  evidencePath?: string | null;
  evidenceUrl?: string | null;
  assignedOfficer?: string;
  adminNotes?: string;
  createdAt?: string;
  updatedAt?: string;
}

export interface ComplaintStats {
  total: number;
  pending: number;
  investigating: number;
  resolved: number;
}
