package org.vufind;

public class LoanRule {
	private Long loanRuleId;
	private String name;
	private Boolean holdable;
	public Long getLoanRuleId() {
		return loanRuleId;
	}
	public void setLoanRuleId(Long loanRuleId) {
		this.loanRuleId = loanRuleId;
	}
	public String getName() {
		return name;
	}
	public void setName(String name) {
		this.name = name;
	}
	public Boolean getHoldable() {
		return holdable;
	}
	public void setHoldable(Boolean holdable) {
		this.holdable = holdable;
	}
	
	
}